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

    /**
     * Everything the Student's Billing Form shows: the student and their
     * programme, the courses billed (credits x rate), the registration fees and
     * the installment totals. Null when the invoice does not exist.
     */
    public function billingForm(int $invoiceId): ?array
    {
        $header = Database::selectOne(
            'SELECT i.*, s.admission_number, s.year_of_study, s.current_semester, s.nationality,
                    u.first_name, u.last_name, u.other_name, u.gender, u.phone, u.email,
                    p.name AS program_name, d.name AS department_name, f.name AS college_name,
                    sem.name AS semester_name, ay.name AS academic_year
               FROM invoices i
               JOIN students s        ON s.id = i.student_id
               JOIN users u           ON u.id = s.user_id
               JOIN programs p        ON p.id = s.program_id
               JOIN departments d     ON d.id = p.department_id
               JOIN faculties f       ON f.id = d.faculty_id
          LEFT JOIN semesters sem     ON sem.id = i.semester_id
          LEFT JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE i.id = ?',
            [$invoiceId]
        );
        if ($header === null) {
            return null;
        }

        $courses = Database::select(
            'SELECT ii.*, c.code, c.title, o.section, CONCAT(lu.first_name, " ", lu.last_name) AS instructor,
                    (SELECT GROUP_CONCAT(a.code ORDER BY a.code SEPARATOR ", ") FROM course_aliases a
                      WHERE a.course_id = c.id AND a.code != c.code) AS billing_codes
               FROM invoice_items ii
               JOIN invoices i           ON i.id = ii.invoice_id
               JOIN courses c            ON c.id = ii.course_id
          LEFT JOIN course_offerings o   ON o.course_id = c.id AND o.semester_id = i.semester_id
                                        AND o.id IN (SELECT cr.offering_id FROM course_registrations cr
                                                      WHERE cr.student_id = i.student_id AND cr.semester_id = i.semester_id
                                                        AND cr.status = "registered")
          LEFT JOIN staff ls             ON ls.id = o.lecturer_id
          LEFT JOIN users lu             ON lu.id = ls.user_id
              WHERE ii.invoice_id = ? AND ii.course_id IS NOT NULL
              ORDER BY ii.id',
            [$invoiceId]
        );
        $fees = Database::select(
            'SELECT ii.* FROM invoice_items ii WHERE ii.invoice_id = ? AND ii.course_id IS NULL ORDER BY ii.id',
            [$invoiceId]
        );

        return [
            'invoice'      => $header,
            'standing'     => FeeStructure::classStanding($header),
            'courses'      => $courses,
            'fees'         => $fees,
            'credits'      => array_sum(array_column($courses, 'quantity')),
            'tuition'      => round(array_sum(array_column($courses, 'amount')), 2),
            'feesTotal'    => round(array_sum(array_column($fees, 'amount')), 2),
            'installments' => $this->installments($invoiceId),
        ];
    }

    public function installments(int $invoiceId): array
    {
        return Database::select(
            'SELECT * FROM invoice_installments WHERE invoice_id = ? ORDER BY installment_number',
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

        $this->allocateToInstallments($invoiceId, $paid);
    }

    /** Apply the amount paid to installments in order: the 1st is cleared before the 2nd, and so on. */
    private function allocateToInstallments(int $invoiceId, float $paid): void
    {
        $remaining = $paid;
        foreach ($this->installments($invoiceId) as $installment) {
            $amount    = (float) $installment['amount'];
            $applied   = round(min($amount, max(0.0, $remaining)), 2);
            $remaining = round($remaining - $applied, 2);

            $status = match (true) {
                $applied >= $amount - 0.005                                                => 'paid',
                $applied > 0                                                               => 'partial',
                $installment['due_date'] !== null && $installment['due_date'] < date('Y-m-d') => 'overdue',
                default                                                                    => 'unpaid',
            };
            Database::statement(
                'UPDATE invoice_installments SET amount_paid = ?, status = ? WHERE id = ?',
                [$applied, $status, $installment['id']]
            );
        }
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
