<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;
use App\Core\Setting;

final class BookLoan extends Model
{
    protected string $table = 'book_loans';
    protected bool $timestamps = false;
    protected array $fillable = [
        'book_id', 'user_id', 'issued_by', 'issued_at', 'due_date', 'returned_at',
        'received_by', 'renewals', 'fine_amount', 'fine_paid', 'status', 'remarks',
    ];
    protected array $searchable = ['b.title', 'b.accession_number', 'u.first_name', 'u.last_name'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('book_loans', 'bl')
            ->select(
                'bl.*', 'b.title', 'b.author', 'b.accession_number',
                'u.first_name', 'u.last_name', 'u.user_type', 'u.email',
                'DATEDIFF(CURDATE(), bl.due_date) AS days_overdue'
            )
            ->join('books b', 'b.id = bl.book_id')
            ->join('users u', 'u.id = bl.user_id');
    }

    /** Issue a copy to a borrower. */
    public function issue(int $bookId, int $userId, ?int $issuedBy = null, ?int $loanDays = null): array
    {
        $book = Database::selectOne('SELECT * FROM books WHERE id = ?', [$bookId]);
        if ($book === null) {
            return ['ok' => false, 'message' => 'Book not found.'];
        }
        if ((int) $book['available_copies'] < 1) {
            return ['ok' => false, 'message' => 'No copies of this title are available.'];
        }

        $limit  = (int) Setting::get('library_borrow_limit', 3);
        $onLoan = (int) Database::scalar(
            'SELECT COUNT(*) FROM book_loans WHERE user_id = ? AND status IN ("borrowed","overdue")',
            [$userId]
        );
        if ($onLoan >= $limit) {
            return ['ok' => false, 'message' => "Borrowing limit reached ({$limit} titles)."];
        }

        $days = $loanDays ?? (int) Setting::get('library_loan_days', 14);
        $id   = Database::transaction(function () use ($bookId, $userId, $issuedBy, $days) {
            $loanId = $this->create([
                'book_id'   => $bookId,
                'user_id'   => $userId,
                'issued_by' => $issuedBy,
                'issued_at' => date('Y-m-d H:i:s'),
                'due_date'  => date('Y-m-d', strtotime("+{$days} days")),
                'status'    => 'borrowed',
            ]);
            Database::statement(
                'UPDATE books SET available_copies = GREATEST(available_copies - 1, 0) WHERE id = ?',
                [$bookId]
            );
            return $loanId;
        });

        return ['ok' => true, 'message' => 'Book issued. Due ' . date('d M Y', strtotime("+{$days} days")) . '.', 'id' => $id];
    }

    /** Accept a return and compute any overdue fine. */
    public function receiveReturn(int $loanId, ?int $receivedBy = null, string $condition = 'returned'): array
    {
        $loan = $this->find($loanId);
        if ($loan === null) {
            return ['ok' => false, 'message' => 'Loan not found.'];
        }
        if ($loan['returned_at'] !== null) {
            return ['ok' => false, 'message' => 'This copy has already been returned.'];
        }

        $daysLate = max(0, (int) floor((time() - strtotime((string) $loan['due_date'])) / 86400));
        $rate     = (float) Setting::get('library_fine_per_day', 20);
        $fine     = round($daysLate * $rate, 2);

        Database::transaction(function () use ($loanId, $loan, $receivedBy, $fine, $condition) {
            $this->update($loanId, [
                'returned_at' => date('Y-m-d H:i:s'),
                'received_by' => $receivedBy,
                'fine_amount' => $fine,
                'status'      => $condition,
            ]);
            if ($condition !== 'lost') {
                Database::statement(
                    'UPDATE books SET available_copies = LEAST(available_copies + 1, total_copies) WHERE id = ?',
                    [$loan['book_id']]
                );
            } else {
                Database::statement(
                    'UPDATE books SET total_copies = GREATEST(total_copies - 1, 0) WHERE id = ?',
                    [$loan['book_id']]
                );
            }
        });

        $message = $fine > 0
            ? 'Return recorded. Overdue fine: ' . money($fine) . ' (' . $daysLate . ' day(s) late).'
            : 'Return recorded. No fine due.';

        return ['ok' => true, 'message' => $message, 'fine' => $fine];
    }

    public function flagOverdue(): int
    {
        return Database::statement(
            'UPDATE book_loans SET status = "overdue"
              WHERE status = "borrowed" AND due_date < CURDATE()'
        );
    }
}
