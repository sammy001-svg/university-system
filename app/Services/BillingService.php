<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Notify;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SemesterEnrollment;

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
        $student = $this->studentForBilling($studentId);
        if ($student === null) {
            return ['ok' => false, 'message' => 'Student not found.'];
        }

        $semester = $this->semesterForBilling($semesterId);
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

        $lines = $this->billLines($structure, $student, $semesterId);
        if ($lines === []) {
            return ['ok' => false, 'message' => 'The fee structure has no items and the student has no registered courses.'];
        }

        $invoiceId = Database::transaction(function () use ($semester, $structure, $lines, $issuedBy, $studentId, $semesterId) {
            $discount = $this->scholarshipDiscount($studentId, (int) $semester['ay_id'], $lines);

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

            $this->writeLines($invoiceId, $lines);
            $this->writeInstallments($invoiceId, $structure, $semester, $lines, $discount, null);

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

    /**
     * Bring a student's semester bill in line with their current course
     * registrations: raise it if there is none yet, otherwise rebuild the
     * per-credit tuition lines and the installments. Once registration is
     * complete (1st installment paid) the 1st installment is kept as it was and
     * any change in tuition falls on the later installments.
     * @return array{ok:bool,message:string,invoice_id?:int}
     */
    public function refreshSemesterBill(int $studentId, int $semesterId, ?int $issuedBy = null): array
    {
        $invoice = Database::selectOne(
            'SELECT i.*, fs.tuition_basis FROM invoices i LEFT JOIN fee_structures fs ON fs.id = i.fee_structure_id
              WHERE i.student_id = ? AND i.semester_id = ? AND i.status != "cancelled" ORDER BY i.id DESC LIMIT 1',
            [$studentId, $semesterId]
        );
        if ($invoice === null) {
            return $this->invoiceStudent($studentId, $semesterId, $issuedBy);
        }
        if ($invoice['tuition_basis'] !== 'per_credit') {
            return ['ok' => true, 'message' => 'Flat-rate bill; nothing to recalculate.', 'invoice_id' => (int) $invoice['id']];
        }

        $student   = $this->studentForBilling($studentId);
        $semester  = $this->semesterForBilling($semesterId);
        $structure = $this->structures->find((int) $invoice['fee_structure_id']);
        $tuition   = $this->tuitionLines($student, $semesterId);
        $invoiceId = (int) $invoice['id'];

        Database::transaction(function () use ($invoiceId, $invoice, $tuition, $structure, $semester, $studentId, $semesterId) {
            Database::statement('DELETE FROM invoice_items WHERE invoice_id = ? AND course_id IS NOT NULL', [$invoiceId]);
            $this->writeLines($invoiceId, $tuition);

            $lines    = $this->invoices->items($invoiceId);
            $discount = $this->scholarshipDiscount($studentId, (int) $semester['ay_id'], $lines);
            Database::statement('UPDATE invoices SET discount_amount = ? WHERE id = ?', [$discount, $invoiceId]);

            $keepFirst = null;
            if ((new SemesterEnrollment())->isEnrolled($studentId, $semesterId)) {
                $keepFirst = (float) Database::scalar(
                    'SELECT amount FROM invoice_installments WHERE invoice_id = ? AND installment_number = 1',
                    [$invoiceId]
                );
            }
            $this->writeInstallments($invoiceId, $structure, $semester, $lines, $discount, $keepFirst);
            $this->refresh($invoiceId);
        });

        return ['ok' => true, 'message' => 'Bill updated to the registered courses.', 'invoice_id' => $invoiceId];
    }

    /**
     * Per-credit tuition for the courses a student is registered for in a
     * semester: credit hours (as their programme counts them) times the
     * course's tuition rate.
     * @return list<array{fee_type_id:?int,course_id:int,description:string,quantity:int,unit_amount:float,amount:float,category:string}>
     */
    public function tuitionLines(array $student, int $semesterId): array
    {
        $tuitionType = Database::scalar('SELECT id FROM fee_types WHERE code = "TUI" LIMIT 1');
        $rows = Database::select(
            'SELECT c.id, c.code, c.title,
                    COALESCE(cr.credit_hours,
                             (SELECT pc.credit_hours FROM program_courses pc
                               WHERE pc.program_id = ? AND pc.course_id = c.id ORDER BY pc.year_of_study LIMIT 1),
                             c.credit_hours) AS credits,
                    COALESCE(tr.rate_per_credit, general.rate_per_credit, 0) AS rate
               FROM course_registrations cr
               JOIN course_offerings o ON o.id = cr.offering_id
               JOIN courses c          ON c.id = o.course_id
          LEFT JOIN tuition_rates tr   ON tr.code = c.tuition_rate_code AND tr.status = "active"
          LEFT JOIN tuition_rates general ON general.code = "GENERAL"
              WHERE cr.student_id = ? AND cr.semester_id = ? AND cr.status = "registered"
              ORDER BY cr.id',
            [(int) $student['program_id'], (int) $student['id'], $semesterId]
        );
        return array_map(static fn (array $r): array => [
            'fee_type_id' => $tuitionType === null ? null : (int) $tuitionType,
            'course_id'   => (int) $r['id'],
            'description' => $r['code'] . ' ' . $r['title'],
            'quantity'    => (int) $r['credits'],
            'unit_amount' => (float) $r['rate'],
            'amount'      => round((int) $r['credits'] * (float) $r['rate'], 2),
            'category'    => 'tuition',
        ], $rows);
    }

    /** Every line of a new bill: the structure's fees, plus per-credit tuition when it bills that way. */
    private function billLines(array $structure, array $student, int $semesterId): array
    {
        $perCredit = ($structure['tuition_basis'] ?? 'flat') === 'per_credit';
        $lines     = [];
        foreach ($this->structures->items((int) $structure['id']) as $item) {
            if ($perCredit && $item['category'] === 'tuition') {
                continue; // tuition comes from the registered courses instead
            }
            $lines[] = [
                'fee_type_id' => (int) $item['fee_type_id'],
                'course_id'   => null,
                'description' => $item['fee_type_name'],
                'quantity'    => 1,
                'unit_amount' => (float) $item['amount'],
                'amount'      => (float) $item['amount'],
                'category'    => $item['category'],
            ];
        }
        return $perCredit ? array_merge($this->tuitionLines($student, $semesterId), $lines) : $lines;
    }

    private function writeLines(int $invoiceId, array $lines): void
    {
        foreach ($lines as $line) {
            Database::statement(
                'INSERT INTO invoice_items (invoice_id, fee_type_id, course_id, description, quantity, unit_amount, amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$invoiceId, $line['fee_type_id'], $line['course_id'], $line['description'], $line['quantity'], $line['unit_amount'], $line['amount']]
            );
        }
        Database::statement(
            'UPDATE invoices SET total_amount = (SELECT COALESCE(SUM(amount), 0) FROM invoice_items WHERE invoice_id = ?) WHERE id = ?',
            [$invoiceId, $invoiceId]
        );
    }

    /**
     * (Re)write the installments from the structure's plan. $keepFirst pins the
     * 1st installment (registration already complete); the difference moves to
     * the last installment.
     */
    private function writeInstallments(int $invoiceId, array $structure, array $semester, array $lines, float $discount, ?float $keepFirst): void
    {
        $tuition = 0.0;
        $other   = 0.0;
        foreach ($lines as $line) {
            ($line['category'] ?? '') === 'tuition' ? $tuition += (float) $line['amount'] : $other += (float) $line['amount'];
        }
        $split = FeeStructure::splitIntoInstallments($this->structures->installments((int) $structure['id']), $tuition, $other, $discount);

        $last = count($split) - 1;
        if ($keepFirst !== null && $last > 0) {
            $split[$last]['amount'] = round($split[$last]['amount'] + $split[0]['amount'] - $keepFirst, 2);
            $split[0]['amount']     = $keepFirst;
            if ($split[$last]['amount'] < 0) { // tuition fell below what is already due now
                $split[0]['amount']     = round($split[0]['amount'] + $split[$last]['amount'], 2);
                $split[$last]['amount'] = 0.0;
            }
        }

        Database::statement('DELETE FROM invoice_installments WHERE invoice_id = ?', [$invoiceId]);
        foreach ($split as $installment) {
            Database::statement(
                'INSERT INTO invoice_installments (invoice_id, installment_number, label, amount, due_date) VALUES (?, ?, ?, ?, ?)',
                [
                    $invoiceId,
                    $installment['installment_number'],
                    $installment['label'],
                    $installment['amount'],
                    date('Y-m-d', strtotime($semester['start_date'] . ' ' . sprintf('%+d', $installment['due_days']) . ' days')),
                ]
            );
        }
        Database::statement(
            'UPDATE invoices SET balance = GREATEST(0, total_amount - discount_amount - amount_paid) WHERE id = ?',
            [$invoiceId]
        );
    }

    private function studentForBilling(int $studentId): ?array
    {
        return Database::selectOne(
            'SELECT s.*, p.name AS program_name, p.department_id, d.faculty_id
               FROM students s
               JOIN programs p    ON p.id = s.program_id
               JOIN departments d ON d.id = p.department_id
              WHERE s.id = ?',
            [$studentId]
        );
    }

    private function semesterForBilling(int $semesterId): ?array
    {
        return Database::selectOne(
            'SELECT sem.*, ay.id AS ay_id, ay.name AS academic_year
               FROM semesters sem JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE sem.id = ?',
            [$semesterId]
        );
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
