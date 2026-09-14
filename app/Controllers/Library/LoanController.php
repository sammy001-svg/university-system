<?php
declare(strict_types=1);

namespace App\Controllers\Library;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\BookLoan;

final class LoanController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('library.view');
        $loans = Database::select(
            'SELECT bl.*, b.title, b.accession_number, b.author,
                    s.admission_number, u.first_name, u.last_name
               FROM book_loans bl
               JOIN books b    ON b.id  = bl.book_id
               JOIN users u    ON u.id  = bl.user_id
               JOIN students s ON s.user_id = bl.user_id
              ORDER BY bl.issued_at DESC
              LIMIT 200'
        );
        return $this->view('library.loans.index', ['pageTitle' => 'Book Loans', 'loans' => $loans]);
    }

    public function issueForm(Request $request): string
    {
        $this->authorize('library.issue');
        return $this->view('library.loans.issue', [
            'pageTitle' => 'Issue Book',
            'books'     => Database::select("SELECT id, CONCAT(accession_number,' – ',title) AS label FROM books WHERE available_copies>0 ORDER BY title"),
            'students'  => Database::select("SELECT s.id, CONCAT(s.admission_number,' – ',u.first_name,' ',u.last_name) AS label FROM students s JOIN users u ON u.id=s.user_id WHERE s.status='active' ORDER BY u.last_name"),
        ]);
    }

    public function issue(Request $request): never
    {
        $this->authorize('library.issue');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'book_id'    => 'required|integer|exists:books,id',
            'student_id' => 'required|integer|exists:students,id',
        ]);

        $loanDays = (int) \App\Core\Setting::get('library_loan_days', 14);
        $due = date('Y-m-d', strtotime("+{$loanDays} days"));

        // book_loans.user_id references users.id; we need the user_id of the student.
        $userId = (int) Database::scalar('SELECT user_id FROM students WHERE id = ?', [$data['student_id']]);

        Database::statement(
            'INSERT INTO book_loans (book_id, user_id, issued_at, due_date, issued_by, status) VALUES (?,?,NOW(),?,?,"borrowed")',
            [$data['book_id'], $userId, $due, Auth::id()]
        );
        Database::statement('UPDATE books SET available_copies = available_copies - 1 WHERE id=?', [$data['book_id']]);
        $this->success('Book issued. Due: ' . $due, '/library/loans');
    }

    public function receive(Request $request, string $id): never
    {
        $this->authorize('library.issue');
        $this->verifyCsrf($request);
        $loan = Database::selectOne('SELECT * FROM book_loans WHERE id=?', [(int)$id]);
        if (!$loan) { throw new HttpException(404); }

        Database::statement("UPDATE book_loans SET returned_at=NOW(), status='returned', received_by=? WHERE id=?", [Auth::id(), (int)$id]);
        Database::statement('UPDATE books SET available_copies = available_copies + 1 WHERE id=?', [$loan['book_id']]);
        $this->success('Book returned.', '/library/loans');
    }

    public function renew(Request $request, string $id): never
    {
        $this->authorize('library.issue');
        $this->verifyCsrf($request);
        $maxRenewals = (int) \App\Core\Setting::get('library_max_renewals', 2);
        $loan = Database::selectOne('SELECT * FROM book_loans WHERE id=?', [(int)$id]);
        if (!$loan) { throw new HttpException(404); }
        if ((int)$loan['renewals'] >= $maxRenewals) { $this->error("Maximum {$maxRenewals} renewals reached.", '/library/loans'); }

        $loanDays = (int) \App\Core\Setting::get('library_loan_days', 14);
        $due = date('Y-m-d', strtotime("+{$loanDays} days"));
        Database::statement('UPDATE book_loans SET due_date=?, renewals=renewals+1, status="borrowed" WHERE id=?', [$due, (int)$id]);
        $this->success('Loan renewed until ' . $due, '/library/loans');
    }
}
