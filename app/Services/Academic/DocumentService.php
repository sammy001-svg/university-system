<?php
declare(strict_types=1);

namespace App\Services\Academic;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Setting;
use App\Models\FeeStructure;
use App\Models\SemesterEnrollment;
use App\Services\Alerts\Alerts;
use RuntimeException;

/**
 * Official documents: admission and enrolment letters, transcripts, student
 * ID cards and clearance letters. Each gets a serial number and a code; the
 * QR code printed on it opens /verify/{code}, where anyone can confirm the
 * document is genuine and still valid.
 */
final class DocumentService
{
    public const TYPES = [
        'enrollment_letter' => 'Enrolment verification letter',
        'admission_letter'  => 'Admission letter',
        'transcript'        => 'Official transcript',
        'id_card'           => 'Student ID card',
        'clearance_letter'  => 'Clearance letter',
    ];
    private const SERIAL_CODES = ['enrollment_letter' => 'ENR', 'admission_letter' => 'ADM', 'transcript' => 'TRN', 'id_card' => 'IDC', 'clearance_letter' => 'CLR'];

    public static function student(int $studentId): ?array
    {
        return Database::selectOne(
            'SELECT s.*, u.first_name, u.last_name, u.other_name, u.gender, u.date_of_birth, u.avatar, u.email,
                    p.name AS program_name, p.code AS program_code, p.level AS program_level, d.name AS department_name, f.name AS faculty_name
               FROM students s JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
               JOIN departments d ON d.id = p.department_id JOIN faculties f ON f.id = d.faculty_id
              WHERE s.id = ?',
            [$studentId]
        );
    }

    /** Issue a document now; throws when the student does not qualify for it. */
    public static function issue(string $type, int $studentId, ?int $issuedBy): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException('Unknown document type.');
        }
        $s = self::student($studentId);
        if ($s === null) {
            throw new RuntimeException('Student not found.');
        }
        $semester = Database::selectOne('SELECT s.*, ay.name AS academic_year, ay.end_date AS year_end FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id WHERE s.is_current = 1 LIMIT 1');
        $name     = trim($s['first_name'] . ' ' . ($s['other_name'] ?? '') . ' ' . $s['last_name']);
        $summary  = ['Name' => (string) preg_replace('/\s+/', ' ', $name), 'Student ID' => $s['admission_number'], 'Programme' => $s['program_name']];
        $validUntil = null;

        switch ($type) {
            case 'enrollment_letter':
                if ($semester === null || !(new SemesterEnrollment())->isEnrolled($studentId, (int) $semester['id'])) {
                    throw new RuntimeException('The student is not enrolled for the current semester (1st installment unpaid), so an enrolment letter cannot be issued.');
                }
                $summary += ['Enrolled for' => $semester['academic_year'] . ' ' . $semester['name'], 'Class standing' => FeeStructure::classStanding($s)];
                $validUntil = $semester['end_date'];
                break;
            case 'admission_letter':
                $summary += ['Admitted' => $s['admission_date'] ? fdate($s['admission_date']) : 'On record'];
                break;
            case 'transcript':
                $gpa = Database::selectOne(
                    'SELECT SUM(quality_points) AS qp, SUM(credit_hours) AS ch, SUM(CASE WHEN outcome = "pass" THEN credit_hours ELSE 0 END) AS earned, COUNT(*) AS n
                       FROM course_results WHERE student_id = ? AND is_published = 1',
                    [$studentId]
                );
                if ((int) $gpa['n'] === 0) {
                    throw new RuntimeException('The student has no published results yet.');
                }
                $summary += ['CGPA' => number_format((float) $gpa['qp'] / max(1, (float) $gpa['ch']), 2), 'Credits earned' => (string) (int) $gpa['earned'],
                             'Courses on record' => (string) (int) $gpa['n']];
                break;
            case 'id_card':
                $summary += ['Status' => ucfirst((string) $s['status'])];
                $validUntil = $semester['year_end'] ?? date('Y-m-d', strtotime('+1 year'));
                break;
            case 'clearance_letter':
                $open = (int) Database::scalar('SELECT COUNT(*) FROM clearances WHERE student_id = ? AND status <> "cleared"', [$studentId]);
                $done = (int) Database::scalar('SELECT COUNT(*) FROM clearances WHERE student_id = ?', [$studentId]);
                if ($done === 0 || $open > 0) {
                    throw new RuntimeException('Every clearance unit must sign off before a clearance letter can be issued.');
                }
                $summary += ['Clearance' => 'Cleared by all units'];
                break;
        }
        if ($s['status'] !== 'active' && in_array($type, ['enrollment_letter', 'id_card'], true)) {
            throw new RuntimeException('Only an active student can be issued this document.');
        }

        $year   = date('Y');
        $prefix = (string) preg_replace('/[^A-Z0-9]/', '', strtoupper((string) Setting::get('institution_short_name', 'UNI')));
        $next   = (int) Database::scalar('SELECT COUNT(*) FROM issued_documents WHERE doc_type = ? AND YEAR(issued_at) = ?', [$type, $year]) + 1;
        do {
            $serial = sprintf('%s-%s-%s-%05d', $prefix, self::SERIAL_CODES[$type], $year, $next++);
        } while (Database::scalar('SELECT 1 FROM issued_documents WHERE serial = ?', [$serial]));
        $code = self::newCode();

        Database::statement(
            'INSERT INTO issued_documents (serial, verification_code, doc_type, student_id, summary, issued_by, issued_at, valid_until)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$serial, $code, $type, $studentId, json_encode($summary, JSON_UNESCAPED_UNICODE), $issuedBy, date('Y-m-d H:i:s'), $validUntil]
        );
        $id = Database::lastInsertId();
        Audit::log('issue', 'documents', 'issued_documents', (string) $id, 'Issued ' . self::TYPES[$type] . ' ' . $serial . ' to ' . $s['admission_number']);
        if ($issuedBy !== null && $issuedBy !== (int) $s['user_id']) {
            Alerts::send((int) $s['user_id'], 'document_issued', self::TYPES[$type] . ' issued',
                'Your ' . strtolower(self::TYPES[$type]) . ' (' . $serial . ') is ready.', '/portal/documents', 'success', 'file');
        }
        return self::find($id);
    }

    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM issued_documents WHERE id = ?', [$id]);
    }

    /** Public verification; counts the check. */
    public static function verify(string $code): ?array
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
        $doc  = Database::selectOne(
            'SELECT d.*, s.status AS student_status FROM issued_documents d JOIN students s ON s.id = d.student_id WHERE d.verification_code = ?',
            [$code]
        );
        if ($doc !== null) {
            Database::statement('UPDATE issued_documents SET verify_count = verify_count + 1 WHERE id = ?', [$doc['id']]);
        }
        return $doc;
    }

    public static function revoke(int $id, string $reason, int $userId): void
    {
        Database::statement('UPDATE issued_documents SET status = "revoked", revoked_reason = ? WHERE id = ?', [mb_substr($reason, 0, 255), $id]);
        Audit::log('revoke', 'documents', 'issued_documents', (string) $id, 'Revoked: ' . $reason);
    }

    /** Ten characters, no look-alikes (0/O, 1/I). */
    private static function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Database::scalar('SELECT 1 FROM issued_documents WHERE verification_code = ?', [$code]));
        return $code;
    }

    /** Published results grouped by term, for the transcript. */
    public static function transcript(int $studentId): array
    {
        $terms = [];
        foreach (Database::select(
            'SELECT r.*, c.code, c.title, sem.name AS semester_name, ay.name AS academic_year
               FROM course_results r JOIN courses c ON c.id = r.course_id
               JOIN semesters sem ON sem.id = r.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE r.student_id = ? AND r.is_published = 1
              ORDER BY ay.start_date, sem.semester_number, c.code',
            [$studentId]
        ) as $r) {
            $terms[$r['academic_year'] . ' ' . $r['semester_name']][] = $r;
        }
        return $terms;
    }
}
