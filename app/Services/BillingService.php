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
    public function invoiceStudent(int $studentId, int $semesterId, ?int $issuedBy = null, ?int $onlyStructureId = null): array
    {
        $student = Database::selectOne(
            'SELECT s.*, p.name AS program_name, p.department_id, d.faculty_id
               FROM students s
               JOIN programs p    ON p.id = s.program_id
               JOIN departments d ON d.id = p.department_id
              WHERE s.id = ?',
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

        $structure = $this->structures->resolveFor($student, $semester);
        if ($structure === null) {
            return ['ok' => false, 'message' => 'No active fee structure matches this student for the semester.'];
        }
        if ($onlyStructureId !== null && (int) $structure['id'] !== $onlyStructureId) {
            return ['ok' => false, 'message' => 'A more specific fee structure applies to this student.'];
        }

        $items = $this->structures->items((int) $structure['id']);
        if ($items === []) {
            return ['ok' => false, 'message' => 'The fee structure has no items.'];
        }

        $invoiceId = Database::transaction(function () use ($semester, $structure, $items, $issuedBy, $studentId, $semesterId) {
            $total    = 0.0;
            $tuition  = 0.0;
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
                if ($item['category'] === 'tuition') {
                    $tuition += $amount;
                }
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

            $plan = $this->structures->installments((int) $structure['id']);
            foreach (FeeStructure::splitIntoInstallments($plan, $tuition, $total - $tuition, $discount) as $installment) {
                Database::statement(
                    'INSERT INTO invoice_installments (invoice_id, installment_number, label, amount, due_date)
                     VALUES (?, ?, ?, ?, ?)',
                    [
                        $invoiceId,
                        $installment['installment_number'],
                        $installment['label'],
                        $installment['amount'],
                        date('Y-m-d', strtotime($semester['start_date'] . ' ' . sprintf('%+d', $installment['due_days']) . ' days')),
                    ]
                );
            }

            // A fully discounted 1st installment clears enrolment straight away.
            $this->refresh($invoiceId);
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
                $this->refresh($invoiceId);
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
                $this->refresh((int) $payment['invoice_id']);
            }
        });

        return ['ok' => true, 'message' => 'Payment reversed.'];
    }

    /** Cancel an invoice; the student's enrolment for that semester lapses with it. */
    public function cancelInvoice(int $invoiceId): array
    {
        if ($this->invoices->find($invoiceId) === null) {
            return ['ok' => false, 'message' => 'Invoice not found.'];
        }
        Database::transaction(function () use ($invoiceId) {
            $this->invoices->update($invoiceId, ['status' => 'cancelled']);
            $this->syncEnrollment($invoiceId);
        });
        return ['ok' => true, 'message' => 'Invoice cancelled.'];
    }

    /** Restate an invoice and its installments from payments, then update enrolment. */
    private function refresh(int $invoiceId): void
    {
        $this->invoices->recalculate($invoiceId);
        $this->syncEnrollment($invoiceId);
    }

    /**
     * Enrol the student for the invoice's semester once its 1st installment is
     * paid in full (registration complete), and withdraw the enrolment if that
     * stops being true after a reversal or cancellation.
     */
    private function syncEnrollment(int $invoiceId): void
    {
        $invoice = $this->invoices->find($invoiceId);
        if ($invoice === null || $invoice['semester_id'] === null) {
            return;
        }
        $studentId  = (int) $invoice['student_id'];
        $semesterId = (int) $invoice['semester_id'];

        $current = Database::selectOne(
            'SELECT invoice_id, status FROM semester_enrollments WHERE student_id = ? AND semester_id = ?',
            [$studentId, $semesterId]
        );
        // An enrolment that belongs to a different invoice is not this invoice's to change.
        if ($current !== null && $current['invoice_id'] !== null && (int) $current['invoice_id'] !== $invoiceId
            && $invoice['status'] === 'cancelled') {
            return;
        }

        $first = Database::selectOne(
            'SELECT status FROM invoice_installments WHERE invoice_id = ? AND installment_number = 1',
            [$invoiceId]
        );
        $cleared = $invoice['status'] !== 'cancelled'
            && ($first !== null ? $first['status'] === 'paid' : (float) $invoice['balance'] <= 0.005);
        $status  = $cleared ? 'enrolled' : 'pending_payment';

        if ($current !== null && $current['status'] === $status && (int) $current['invoice_id'] === $invoiceId) {
            return;
        }

        Database::statement(
            'INSERT INTO semester_enrollments (student_id, semester_id, invoice_id, status, enrolled_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE invoice_id = VALUES(invoice_id), status = VALUES(status), enrolled_at = VALUES(enrolled_at)',
            [$studentId, $semesterId, $invoiceId, $status, $cleared ? date('Y-m-d H:i:s') : null]
        );

        if ($cleared && ($current === null || $current['status'] !== 'enrolled')) {
            Notify::send(
                (int) Database::scalar('SELECT user_id FROM students WHERE id = ?', [$studentId]),
                'Registration complete',
                'Your 1st installment is paid. You are enrolled for the semester and may now register for courses.',
                '/portal/registration',
                'success',
                'check'
            );
        }
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

        // Narrow to the students inside the structure's scope; invoiceStudent() then
        // skips anyone a more specific structure (or another student status) applies to.
        $sql    = 'SELECT s.id FROM students s
                     JOIN programs p    ON p.id = s.program_id
                     JOIN departments d ON d.id = p.department_id
                    WHERE s.status = "active"';
        $params = [];
        $filters = [
            'program_id'    => 's.program_id',
            'department_id' => 'p.department_id',
            'faculty_id'    => 'd.faculty_id',
            'year_of_study' => 's.year_of_study',
            'study_mode'    => 's.study_mode',
        ];
        foreach ($filters as $column => $field) {
            if ($structure[$column] !== null) {
                $sql     .= " AND {$field} = ?";
                $params[] = $structure[$column];
            }
        }
        $students = Database::select($sql, $params);

        $created = 0;
        foreach ($students as $row) {
            // Override the due date on each generated invoice.
            $result = $this->invoiceStudent((int) $row['id'], (int) $semester['id'], $issuedBy, $feeStructureId);
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
