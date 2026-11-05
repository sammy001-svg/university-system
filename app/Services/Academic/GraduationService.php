<?php
declare(strict_types=1);

namespace App\Services\Academic;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Setting;
use App\Services\Alerts\Alerts;
use RuntimeException;

/**
 * Graduation: a student (or the registrar) applies, the degree audit is checked, the
 * registrar clears and approves the graduand, and on graduation the final CGPA,
 * degree classification and certificate number are recorded and the student becomes
 * an alumnus.  Status flow: pending (applied) -> cleared -> approved -> graduated; or deferred.
 */
final class GraduationService
{
    public const STATUSES = ['pending' => 'Applied', 'cleared' => 'Cleared', 'approved' => 'Approved', 'graduated' => 'Graduated', 'deferred' => 'Deferred'];

    public static function currentYearId(): int
    {
        return (int) (Database::scalar('SELECT academic_year_id FROM semesters WHERE is_current = 1 LIMIT 1')
            ?: Database::scalar('SELECT id FROM academic_years ORDER BY start_date DESC LIMIT 1'));
    }

    /** May the student apply now? A final-year student, or one within two semesters of finishing. */
    public static function canApply(array $student, array $audit): ?string
    {
        if (!in_array($student['status'], ['active'], true)) {
            return 'Only an active student can apply to graduate.';
        }
        if (Database::scalar('SELECT 1 FROM graduations WHERE student_id = ? AND status IN ("pending","cleared","approved","graduated")', [$student['id']])) {
            return 'You have already applied.';
        }
        $finalYear = (int) $student['year_of_study'] >= (int) floor((float) ($audit['student']['duration_years'] ?? 4));
        if (!$finalYear && $audit['remaining'] - $audit['running'] > 0) {
            return 'You can apply in your final year, once the courses still needed are in progress.';
        }
        return null;
    }

    public static function apply(int $studentId, ?string $nameOnCertificate, ?int $byUserId): int
    {
        $student = Database::selectOne('SELECT s.*, u.first_name, u.last_name, u.other_name FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$studentId])
            ?? throw new RuntimeException('Student not found.');
        $name = trim((string) $nameOnCertificate) !== '' ? trim((string) $nameOnCertificate)
            : (string) preg_replace('/\s+/', ' ', trim($student['first_name'] . ' ' . ($student['other_name'] ?? '') . ' ' . $student['last_name']));
        Database::statement(
            'INSERT INTO graduations (student_id, academic_year_id, program_id, name_on_certificate, status, applied_at)
             VALUES (?, ?, ?, ?, "pending", ?)
             ON DUPLICATE KEY UPDATE status = IF(status = "deferred", "pending", status), name_on_certificate = VALUES(name_on_certificate), applied_at = VALUES(applied_at)',
            [$studentId, self::currentYearId(), $student['program_id'], mb_substr($name, 0, 150), date('Y-m-d H:i:s')]
        );
        $id = (int) Database::scalar('SELECT id FROM graduations WHERE student_id = ? AND academic_year_id = ?', [$studentId, self::currentYearId()]);
        Audit::log('graduation_apply', 'graduation', 'graduations', (string) $id, 'Graduation application for ' . $student['admission_number']);
        return $id;
    }

    /** Move a graduand along: clear | approve | graduate | defer | reopen. */
    public static function advance(int $graduationId, string $action, ?string $remarks, ?string $graduationDate, int $userId): string
    {
        $g = Database::selectOne(
            'SELECT g.*, s.user_id, s.admission_number FROM graduations g JOIN students s ON s.id = g.student_id WHERE g.id = ?',
            [$graduationId]
        ) ?? throw new RuntimeException('Record not found.');

        switch ($action) {
            case 'clear':
                $audit = DegreeAudit::for((int) $g['student_id']);
                if ($audit !== null && !$audit['eligible'] && trim((string) $remarks) === '') {
                    throw new RuntimeException($g['admission_number'] . ' does not yet meet every requirement in the degree audit. Add a remark to clear anyway.');
                }
                $open = (int) Database::scalar('SELECT COUNT(*) FROM clearances WHERE student_id = ? AND status <> "cleared"', [$g['student_id']]);
                Database::statement('UPDATE graduations SET status = "cleared", remarks = COALESCE(?, remarks) WHERE id = ?', [$remarks ?: null, $graduationId]);
                return 'Cleared.' . ($open > 0 ? " Note: {$open} clearance item(s) are still pending." : '');
            case 'approve':
                if (!in_array($g['status'], ['cleared', 'approved'], true)) {
                    throw new RuntimeException('Clear the graduand before approving.');
                }
                Database::statement('UPDATE graduations SET status = "approved", approved_by = ?, remarks = COALESCE(?, remarks) WHERE id = ?', [$userId, $remarks ?: null, $graduationId]);
                Alerts::send((int) $g['user_id'], 'document_issued', 'Approved to graduate',
                    'Congratulations! You have been approved to graduate. Details of the ceremony will follow.', '/portal/graduation', 'success', 'check');
                return 'Approved to graduate.';
            case 'graduate':
                if ($g['status'] !== 'approved') {
                    throw new RuntimeException('Only an approved graduand can be graduated.');
                }
                $gpa = Database::selectOne('SELECT SUM(quality_points) AS qp, SUM(credit_hours) AS ch FROM course_results WHERE student_id = ? AND is_published = 1', [$g['student_id']]);
                $cgpa = (float) ($gpa['ch'] ?? 0) > 0 ? round((float) $gpa['qp'] / (float) $gpa['ch'], 2) : 0.0;
                $date = $graduationDate ?: date('Y-m-d');
                $prefix = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper((string) Setting::get('institution_short_name', 'UNI')));
                $n = (int) Database::scalar('SELECT COUNT(*) FROM graduations WHERE certificate_no IS NOT NULL') + 1;
                do {
                    $cert = sprintf('%s/GRAD/%s/%05d', $prefix, substr($date, 0, 4), $n++);
                } while (Database::scalar('SELECT 1 FROM graduations WHERE certificate_no = ?', [$cert]));
                Database::statement(
                    'UPDATE graduations SET status = "graduated", graduation_date = ?, final_cgpa = ?, classification = ?, certificate_no = ? WHERE id = ?',
                    [$date, $cgpa, classify_gpa($cgpa), $cert, $graduationId]
                );
                Database::statement('UPDATE students SET status = "graduated", completion_date = ? WHERE id = ?', [$date, $g['student_id']]);
                Database::statement('INSERT IGNORE INTO alumni_profiles (student_id, updated_at) VALUES (?, ?)', [$g['student_id'], date('Y-m-d H:i:s')]);
                Alerts::send((int) $g['user_id'], 'document_issued', 'Congratulations, graduate!',
                    'You graduated on ' . fdate($date) . ' with ' . classify_gpa($cgpa) . '. Certificate ' . $cert . '. Keep your alumni profile up to date.',
                    '/portal/graduation', 'success', 'check');
                return 'Graduated: ' . classify_gpa($cgpa) . ', certificate ' . $cert . '.';
            case 'defer':
                if (trim((string) $remarks) === '') {
                    throw new RuntimeException('Give the reason for deferring.');
                }
                Database::statement('UPDATE graduations SET status = "deferred", remarks = ? WHERE id = ?', [$remarks, $graduationId]);
                Alerts::send((int) $g['user_id'], 'document_issued', 'Graduation deferred', 'Your graduation has been deferred: ' . $remarks, '/portal/graduation', 'warning', 'clock');
                return 'Deferred.';
        }
        throw new RuntimeException('Unknown action.');
    }
}
