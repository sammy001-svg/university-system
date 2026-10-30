<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/**
 * A student's enrolment for a semester. Students first choose their courses
 * (the semester bill is built from them); once the 1st installment of that
 * bill is fully paid they are "enrolled" - registration complete - and the
 * course list is final.
 */
final class SemesterEnrollment extends Model
{
    protected string $table = 'semester_enrollments';
    protected array $fillable = ['student_id', 'semester_id', 'invoice_id', 'status', 'enrolled_at'];
    protected array $searchable = [];

    /** The registrar's per-semester list: where a student stands before paying. */
    public const STAGES = [
        'planned'       => 'Planned',
        'committed'     => 'Made commitment',
        'not_committed' => 'Not enrolled / no commitment',
    ];

    /**
     * Enrolment status for a student/semester together with the 1st installment
     * that clears it. Returns null when the student has not been billed yet.
     */
    public function forStudent(int $studentId, int $semesterId): ?array
    {
        $invoice = Database::selectOne(
            'SELECT i.id AS invoice_id, i.invoice_number, i.status AS invoice_status,
                    COALESCE(e.status, "pending_payment") AS status, e.enrolled_at,
                    fi.label AS first_label, fi.amount AS first_amount, fi.amount_paid AS first_paid,
                    fi.due_date AS first_due_date
               FROM invoices i
          LEFT JOIN semester_enrollments e ON e.student_id = i.student_id AND e.semester_id = i.semester_id
          LEFT JOIN invoice_installments fi ON fi.invoice_id = i.id AND fi.installment_number = 1
              WHERE i.student_id = ? AND i.semester_id = ? AND i.status != "cancelled"
              ORDER BY i.id DESC LIMIT 1',
            [$studentId, $semesterId]
        );
        if ($invoice === null) {
            return null;
        }
        $invoice['first_outstanding'] = max(0.0, round((float) $invoice['first_amount'] - (float) $invoice['first_paid'], 2));
        return $invoice;
    }

    public function isEnrolled(int $studentId, int $semesterId): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM semester_enrollments WHERE student_id = ? AND semester_id = ? AND status = "enrolled"',
            [$studentId, $semesterId]
        ) > 0;
    }

    /**
     * Students choose their courses, the bill follows, and paying the 1st
     * installment completes registration. After that the course list is
     * final: returns the message to show, or null while changes are allowed.
     */
    public function changesLocked(int $studentId, int $semesterId): ?string
    {
        return $this->isEnrolled($studentId, $semesterId)
            ? 'Your registration for this semester is complete. To add or drop a course, please see the Office of Admission and Records.'
            : null;
    }
}
