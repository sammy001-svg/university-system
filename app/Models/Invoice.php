<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Invoice extends Model
{
    protected string $table = 'invoices';
    protected array $fillable = [
        'invoice_number', 'student_id', 'semester_id', 'fee_structure_id', 'title', 'total_amount',
        'discount_amount', 'amount_paid', 'balance', 'issue_date', 'due_date', 'status', 'issued_by', 'notes',
    ];
    protected array $searchable = ['i.invoice_number', 's.admission_number', 'u.first_name', 'u.last_name'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('invoices', 'i')
            ->select(
                'i.*', 's.admission_number', 'u.first_name', 'u.last_name', 'u.email',
                'p.name AS program_name', 'sem.name AS semester_name', 'ay.name AS academic_year'
            )
            ->join('students s', 's.id = i.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('programs p', 'p.id = s.program_id')
            ->leftJoin('semesters sem', 'sem.id = i.semester_id')
            ->leftJoin('academic_years ay', 'ay.id = sem.academic_year_id');
    }

    public function detail(int $invoiceId): ?array
    {
        return $this->listing()->where('i.id', $invoiceId)->first();
    }

    public function items(int $invoiceId): array
    {
        return Database::select(
            'SELECT ii.*, ft.name AS fee_type_name, ft.category
               FROM invoice_items ii
          LEFT JOIN fee_types ft ON ft.id = ii.fee_type_id
              WHERE ii.invoice_id = ? ORDER BY ii.id',
            [$invoiceId]
        );
    }

    public function payments(int $invoiceId): array
    {
        return Database::select(
            'SELECT p.*, CONCAT(u.first_name, " ", u.last_name) AS received_by_name
               FROM payments p
          LEFT JOIN users u ON u.id = p.received_by
              WHERE p.invoice_id = ? AND p.status != "reversed"
              ORDER BY p.paid_at DESC',
            [$invoiceId]
        );
    }

    public function nextInvoiceNumber(): string
    {
        $prefix = 'INV-' . date('Ym') . '-';
        $last   = Database::scalar(
            'SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        );
        $next = $last === null ? 1 : ((int) substr((string) $last, -5)) + 1;
        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /** Recalculate paid/balance/status from confirmed payments. */
    public function recalculate(int $invoiceId): void
    {
        $invoice = $this->find($invoiceId);
        if ($invoice === null) {
            return;
        }
        $paid = (float) Database::scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = ? AND status = "confirmed"',
            [$invoiceId]
        );
        $net     = (float) $invoice['total_amount'] - (float) $invoice['discount_amount'];
        $balance = round($net - $paid, 2);

        $status = match (true) {
            $invoice['status'] === 'cancelled'                              => 'cancelled',
            $balance <= 0.005                                               => 'paid',
            $paid > 0                                                       => 'partial',
            $invoice['due_date'] !== null && $invoice['due_date'] < date('Y-m-d') => 'overdue',
            default                                                         => 'unpaid',
        };

        Database::statement(
            'UPDATE invoices SET amount_paid = ?, balance = ?, status = ? WHERE id = ?',
            [$paid, max(0, $balance), $status, $invoiceId]
        );
    }

    public function outstandingTotal(): float
    {
        return (float) Database::scalar(
            "SELECT COALESCE(SUM(balance), 0) FROM invoices WHERE status IN ('unpaid','partial','overdue')"
        );
    }

    public function markOverdue(): int
    {
        return Database::statement(
            "UPDATE invoices SET status = 'overdue'
              WHERE due_date < CURDATE() AND balance > 0 AND status IN ('unpaid','partial')"
        );
    }
}
