<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Student extends Model
{
    protected string $table = 'students';
    protected array $fillable = [
        'user_id', 'admission_number', 'registration_number', 'program_id', 'intake_id', 'campus_id',
        'year_of_study', 'current_semester', 'study_mode', 'admission_date', 'expected_completion',
        'completion_date', 'sponsor_type', 'sponsor_name', 'nationality', 'national_id', 'passport_number',
        'religion', 'marital_status', 'blood_group', 'disability', 'postal_address', 'physical_address',
        'city', 'county', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
        'previous_school', 'previous_qualification', 'previous_grade', 'cgpa', 'credits_earned', 'status',
    ];
    protected array $searchable = [
        's.admission_number', 's.registration_number', 'u.first_name', 'u.last_name', 'u.email', 'u.phone',
    ];

    /** Full listing query joined to users and programs. */
    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('students', 's')
            ->select(
                's.*',
                'u.first_name', 'u.last_name', 'u.other_name', 'u.email', 'u.phone', 'u.gender',
                'u.avatar', 'u.status AS account_status', 'u.date_of_birth',
                'p.name AS program_name', 'p.code AS program_code', 'p.level AS program_level',
                'd.name AS department_name', 'f.name AS faculty_name'
            )
            ->join('users u', 'u.id = s.user_id')
            ->join('programs p', 'p.id = s.program_id')
            ->join('departments d', 'd.id = p.department_id')
            ->join('faculties f', 'f.id = d.faculty_id')
            ->whereNull('u.deleted_at');
    }

    public function profile(int $studentId): ?array
    {
        return $this->listing()->where('s.id', $studentId)->first();
    }

    public function byUserId(int $userId): ?array
    {
        return $this->listing()->where('s.user_id', $userId)->first();
    }

    public function byAdmissionNumber(string $number): ?array
    {
        return $this->listing()->where('s.admission_number', $number)->first();
    }

    public function fullName(array $student): string
    {
        return trim(implode(' ', array_filter([
            $student['first_name'] ?? null,
            $student['other_name'] ?? null,
            $student['last_name'] ?? null,
        ])));
    }

    /** Next admission number, e.g. MU/BCS/0042/2026 */
    public function nextAdmissionNumber(string $programCode, ?int $year = null): string
    {
        $year   = $year ?? (int) date('Y');
        $prefix = (string) \App\Core\Setting::get('institution_short_name', 'UNI');
        $count  = (int) Database::scalar(
            'SELECT COUNT(*) FROM students WHERE YEAR(COALESCE(admission_date, created_at)) = ?',
            [$year]
        );
        do {
            $count++;
            $number = sprintf('%s/%s/%04d/%d', $prefix, $programCode, $count, $year);
        } while ($this->exists('admission_number', $number));

        return $number;
    }

    public function countByStatus(): array
    {
        $rows = Database::select('SELECT status, COUNT(*) AS total FROM students GROUP BY status');
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['total'];
        }
        return $out;
    }

    /** Registered courses for a semester, with result data when available. */
    public function registrations(int $studentId, ?int $semesterId = null): array
    {
        $sql = 'SELECT cr.*, o.section, o.semester_id, c.code, c.title, c.credit_hours,
                       sem.name AS semester_name, ay.name AS academic_year,
                       res.total_score, res.grade, res.grade_point, res.outcome, res.is_published,
                       CONCAT(lu.first_name, " ", lu.last_name) AS lecturer_name
                  FROM course_registrations cr
                  JOIN course_offerings o ON o.id = cr.offering_id
                  JOIN courses c          ON c.id = o.course_id
                  JOIN semesters sem      ON sem.id = cr.semester_id
                  JOIN academic_years ay  ON ay.id = sem.academic_year_id
             LEFT JOIN course_results res ON res.student_id = cr.student_id AND res.offering_id = cr.offering_id
             LEFT JOIN staff lst          ON lst.id = o.lecturer_id
             LEFT JOIN users lu           ON lu.id = lst.user_id
                 WHERE cr.student_id = ? AND cr.status != "dropped"';
        $params = [$studentId];
        if ($semesterId !== null) {
            $sql     .= ' AND cr.semester_id = ?';
            $params[] = $semesterId;
        }
        $sql .= ' ORDER BY ay.start_date DESC, sem.semester_number DESC, c.code';
        return Database::select($sql, $params);
    }

    /** Outstanding fee balance across all invoices. */
    public function balance(int $studentId): float
    {
        return (float) Database::scalar(
            "SELECT COALESCE(SUM(balance), 0) FROM invoices
              WHERE student_id = ? AND status NOT IN ('cancelled','draft')",
            [$studentId]
        );
    }

    public function totalBilled(int $studentId): float
    {
        return (float) Database::scalar(
            "SELECT COALESCE(SUM(total_amount - discount_amount), 0) FROM invoices
              WHERE student_id = ? AND status NOT IN ('cancelled','draft')",
            [$studentId]
        );
    }

    public function totalPaid(int $studentId): float
    {
        return (float) Database::scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE student_id = ? AND status = 'confirmed'",
            [$studentId]
        );
    }

    /** Semester-by-semester academic history. */
    public function academicHistory(int $studentId): array
    {
        return Database::select(
            'SELECT sr.*, sem.name AS semester_name, sem.semester_number, ay.name AS academic_year
               FROM semester_results sr
               JOIN semesters sem     ON sem.id = sr.semester_id
               JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE sr.student_id = ?
              ORDER BY ay.start_date, sem.semester_number',
            [$studentId]
        );
    }

    /** Overall attendance percentage for the student. */
    public function attendanceRate(int $studentId, ?int $semesterId = null): float
    {
        $sql = 'SELECT COUNT(*) AS total,
                       SUM(CASE WHEN ar.status IN ("present","late") THEN 1 ELSE 0 END) AS attended
                  FROM attendance_records ar
                  JOIN attendance_sessions s ON s.id = ar.session_id
                  JOIN course_offerings o    ON o.id = s.offering_id
                 WHERE ar.student_id = ?';
        $params = [$studentId];
        if ($semesterId !== null) {
            $sql     .= ' AND o.semester_id = ?';
            $params[] = $semesterId;
        }
        $row = Database::selectOne($sql, $params);
        $total = (int) ($row['total'] ?? 0);
        return $total === 0 ? 0.0 : round(((int) $row['attended'] / $total) * 100, 1);
    }

    public function guardians(int $studentId): array
    {
        return Database::select(
            'SELECT * FROM guardians WHERE student_id = ? ORDER BY is_primary DESC, full_name',
            [$studentId]
        );
    }

    /** Dropdown list: "ADM/NO - Full Name". */
    public function options(string $labelColumn = 'name', ?string $orderBy = null, array $where = []): array
    {
        $rows = Database::select(
            "SELECT s.id, CONCAT(s.admission_number, ' - ', u.first_name, ' ', u.last_name) AS label
               FROM students s JOIN users u ON u.id = s.user_id
              WHERE s.status = 'active'
              ORDER BY u.last_name, u.first_name"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['id']] = $row['label'];
        }
        return $out;
    }
}
