<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Notify;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;

/**
 * Invoice generation from fee structures, and payment posting.
 */
final class BillingService
{
    public function __construct(
        private Invoice $invoices = new Invoice(),
        private Payment $payments = new Payment(),
        private FeeStructure $structures = new FeeStructure(),
    ) {
    }

    /**
     * Raise the semester invoice for one student.
     * @return array{ok:bool,message:string,invoice_id?:int}
     */
    public function invoiceStudent(int $studentId, int $semesterId, ?int $issuedBy = null): array
    {
        $student = Database::selectOne(
            'SELECT s.*, p.name AS program_name FROM students s
               JOIN programs p ON p.id = s.program_id WHERE s.id = ?',
            [$studentId]
        );
        if ($student === null) {
            return ['ok' => false, 'message' => 'Student not found.'];
        }

        $semester = Database::selectOne(
            'SELECT sem.*, ay.id AS ay_id, ay.name AS academic_year
               FROM semesters sem JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE sem.id = ?',
            [$semesterId]
        );
        if ($semester === null) {
            return ['ok' => false, 'message' => 'Semester not found.'];
        }

        $existing = Database::selectOne(
            'SELECT id FROM invoices WHERE student_id = ? AND semester_id = ? AND status != "cancelled"',
            [$studentId, $semesterId]
        );
        if ($existing !== null) {
            return ['ok' => false, 'message' => 'This student already has an invoice for the semester.'];
        }

        $structure = $this->structures->resolveFor(
            (int) $student['program_id'],
            (int) $semester['ay_id'],
            (int) $student['year_of_study'],
            (int) $semester['semester_number'],
            (string) $student['study_mode']
        );
        if ($structure === null) {
            return ['ok' => false, 'message' => 'No active fee structure matches this student for the semester.'];
        }

        $items = $this->structures->items((int) $structure['id']);
        if ($items === []) {
            return ['ok' => false, 'message' => 'The fee structure has no items.'];
        }

        $invoiceId = Database::transaction(function () use ($student, $semester, $structure, $items, $issuedBy, $studentId, $semesterId) {
            $total    = 0.0;
            $discount = $this->scholarshipDiscount($studentId, (int) $semester['ay_id'], $items);

            $invoiceId = $this->invoices->create([
                'invoice_number'   => $this->invoices->nextInvoiceNumber(),
                'student_id'       => $studentId,
                'semester_id'      => $semesterId,
                'fee_structure_id' => (int) $structure['id'],
                'title'            => $structure['name'] . ' - ' . $semester['academic_year'] . ' ' . $semester['name'],
                'total_amount'     => 0,
                'discount_amount'  => $discount,
                'balance'          => 0,
                'issue_date'       => date('Y-m-d'),
                'due_date'         => $semester['registration_end'] ?? date('Y-m-d', strtotime('+30 days')),
                'status'           => 'unpaid',
                'issued_by'        => $issuedBy,
            ]);

            foreach ($items as $item) {
                $amount = (float) $item['amount'];
                $total += $amount;
                Database::statement(
                    'INSERT INTO invoice_items (invoice_id, fee_type_id, description, quantity, unit_amount, amount)
                     VALUES (?, ?, ?, 1, ?, ?)',
                    [$invoiceId, $item['fee_type_id'], $item['fee_type_name'], $amount, $amount]
                );
            }

            Database::statement(
                'UPDATE invoices SET total_amount = ?, balance = ? WHERE id = ?',
                [$total, max(0, $total - $discount), $invoiceId]
            );
            return $invoiceId;
        });

        Notify::send(
            (int) $student['user_id'],
            'New fee invoice issued',
            'An invoice has been raised for ' . $semester['academic_year'] . ' ' . $semester['name'] . '.',
            '/portal/finance',
            'info',
            'money'
        );

        return ['ok' => true, 'message' => 'Invoice generated.', 'invoice_id' => $invoiceId];
    }

    /** Bulk billing for every active student in a program/semester. */
    public function invoiceCohort(int $semesterId, ?int $programId = null, ?int $yearOfStudy = null, ?int $issuedBy = null): array
    {
        $sql    = 'SELECT id FROM students WHERE status = "active"';
        $params = [];
        if ($programId !== null) {
            $sql     .= ' AND program_id = ?';
            $params[] = $programId;
        }
        if ($yearOfStudy !== null) {
            $sql     .= ' AND year_of_study = ?';
            $params[] = $yearOfStudy;
        }

        $created = 0;
        $skipped = 0;
        foreach (Database::select($sql, $params) as $row) {
            $result = $this->invoiceStudent((int) $row['id'], $semesterId, $issuedBy);
            if ($result['ok']) {
                $created++;
            } else {
                $skipped++;
            }
        }
        return ['created' => $created, 'skipped' => $skipped];
    }

    private function scholarshipDiscount(int $studentId, int $academicYearId, array $items): float
    {
        $award = Database::selectOne(
            'SELECT sc.award_type, sc.percentage, ss.amount
               FROM student_scholarships ss
               JOIN scholarships sc ON sc.id = ss.scholarship_id
              WHERE ss.student_id = ? AND ss.academic_year_id = ? AND ss.status IN ("approved","active")
              LIMIT 1',
            [$studentId, $academicYearId]
        );
        if ($award === null) {
            return 0.0;
        }

        $tuition = 0.0;
        foreach ($items as $item) {
            if (($item['category'] ?? '') === 'tuition') {
                $tuition += (float) $item['amount'];
            }
        }
        return match ($award['award_type']) {
            'full'         => $tuition,
            'partial'      => round($tuition * ((float) $award['percentage'] / 100), 2),
            'fixed_amount' => (float) $award['amount'],
            default        => 0.0,
        };
    }

    /**
     * Post a payment. When no invoice is given the amount is spread across the
     * student's oldest outstanding invoices.
     * @return array{ok:bool,message:string,receipt?:string}
     */
    public function recordPayment(array $data, ?int $receivedBy = null): array
    {
        $studentId = (int) $data['student_id'];
        $amount    = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'The payment amount must be greater than zero.'];
        }

        $student = Database::selectOne('SELECT id, user_id FROM students WHERE id = ?', [$studentId]);
        if ($student === null) {
            return ['ok' => false, 'message' => 'Student not found.'];
        }

        $receipt = $this->payments->nextReceiptNumber();

        Database::transaction(function () use ($data, $studentId, $amount, $receivedBy, $receipt) {
            $invoiceId = isset($data['invoice_id']) && (int) $data['invoice_id'] > 0
                ? (int) $data['invoice_id']
                : $this->oldestOutstandingInvoice($studentId);

            $this->payments->create([
                'receipt_number' => $receipt,
                'student_id'     => $studentId,
                'invoice_id'     => $invoiceId,
                'amount'         => $amount,
                'method'         => $data['method'] ?? 'cash',
                'reference'      => $data['reference'] ?? null,
                'bank_name'      => $data['bank_name'] ?? null,
                'paid_at'        => $data['paid_at'] ?? date('Y-m-d H:i:s'),
                'received_by'    => $receivedBy,
                'status'         => 'confirmed',
                'notes'          => $data['notes'] ?? null,
            ]);

            if ($invoiceId !== null) {
                $this->invoices->recalculate($invoiceId);
            }
        });

        Notify::send(
            (int) $student['user_id'],
            'Payment received',
            money($amount) . ' has been posted to your account. Receipt ' . $receipt . '.',
            '/portal/finance',
            'success',
            'money'
        );

        return ['ok' => true, 'message' => 'Payment recorded. Receipt ' . $receipt . '.', 'receipt' => $receipt];
    }

    private function oldestOutstandingInvoice(int $studentId): ?int
    {
        $id = Database::scalar(
            'SELECT id FROM invoices
              WHERE student_id = ? AND balance > 0 AND status NOT IN ("cancelled","draft")
              ORDER BY due_date ASC, id ASC LIMIT 1',
            [$studentId]
        );
        return $id === null ? null : (int) $id;
    }

    /** Reverse a payment and restate the invoice. */
    public function reversePayment(int $paymentId, string $reason, ?int $actorId = null): array
    {
        $payment = $this->payments->find($paymentId);
        if ($payment === null) {
            return ['ok' => false, 'message' => 'Payment not found.'];
        }
        if ($payment['status'] === 'reversed') {
            return ['ok' => false, 'message' => 'This payment has already been reversed.'];
        }

        Database::transaction(function () use ($payment, $paymentId, $reason) {
            $this->payments->update($paymentId, [
                'status' => 'reversed',
                'notes'  => trim((string) $payment['notes'] . ' | Reversed: ' . $reason),
            ]);
            if ($payment['invoice_id'] !== null) {
                $this->invoices->recalculate((int) $payment['invoice_id']);
            }
        });

        return ['ok' => true, 'message' => 'Payment reversed.'];
    }

    /** Fee-collection summary for the finance dashboard. */
    public function summary(): array
    {
        return [
            'billed'      => (float) Database::scalar(
                "SELECT COALESCE(SUM(total_amount - discount_amount), 0) FROM invoices WHERE status != 'cancelled'"
            ),
            'collected'   => (float) Database::scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed'"
            ),
            'outstanding' => $this->invoices->outstandingTotal(),
            'overdue'     => (float) Database::scalar(
                "SELECT COALESCE(SUM(balance), 0) FROM invoices WHERE status = 'overdue'"
            ),
            'today'       => (float) Database::scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'confirmed' AND DATE(paid_at) = CURDATE()"
            ),
            'this_month'  => (float) Database::scalar(
                "SELECT COALESCE(SUM(amount), 0) FROM payments
                  WHERE status = 'confirmed' AND YEAR(paid_at) = YEAR(CURDATE()) AND MONTH(paid_at) = MONTH(CURDATE())"
            ),
        ];
    }
    /** Bulk-generate invoices for all eligible active students tied to a fee structure. */
    public function generateInvoices(int $feeStructureId, string $dueDate, ?int $issuedBy = null): int
    {
        $structure = $this->structures->find($feeStructureId);
        if ($structure === null) {
            return 0;
        }

        // Find the current (or most recent active) semester.
        $semester = Database::selectOne(
            'SELECT * FROM semesters WHERE is_current = 1 LIMIT 1'
        );
        if ($semester === null) {
            $semester = Database::selectOne(
                'SELECT * FROM semesters WHERE status = "active" ORDER BY id DESC LIMIT 1'
            );
        }
        if ($semester === null) {
            return 0;
        }

        // Match students by program, year, semester_number and study_mode.
        $students = Database::select(
            'SELECT id FROM students
              WHERE status = "active"
                AND program_id = ?
                AND year_of_study = ?
                AND current_semester = ?
                AND study_mode = ?',
            [
                $structure['program_id'],
                $structure['year_of_study'],
                $structure['semester_number'],
                $structure['study_mode'],
            ]
        );

        $created = 0;
        foreach ($students as $row) {
            // Override the due date on each generated invoice.
            $result = $this->invoiceStudent((int) $row['id'], (int) $semester['id'], $issuedBy);
            if ($result['ok']) {
                // Apply the custom due_date.
                if (!empty($dueDate) && isset($result['invoice_id'])) {
                    Database::statement(
                        'UPDATE invoices SET due_date = ? WHERE id = ?',
                        [$dueDate, $result['invoice_id']]
                    );
                }
                $created++;
            }
        }
        return $created;
    }
}
