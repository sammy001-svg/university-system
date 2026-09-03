<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Staff extends Model
{
    protected string $table = 'staff';
    protected array $fillable = [
        'user_id', 'staff_number', 'department_id', 'designation', 'staff_category', 'employment_type',
        'qualification', 'specialization', 'date_joined', 'contract_end', 'salary_grade', 'basic_salary',
        'bank_name', 'bank_branch', 'bank_account', 'tax_pin', 'nssf_number', 'nhif_number',
        'national_id', 'address', 'status',
    ];
    protected array $searchable = [
        's.staff_number', 'u.first_name', 'u.last_name', 'u.email', 'u.phone', 's.designation',
    ];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('staff', 's')
            ->select(
                's.*',
                'u.title', 'u.first_name', 'u.last_name', 'u.email', 'u.phone', 'u.gender',
                'u.avatar', 'u.status AS account_status',
                'd.name AS department_name', 'f.name AS faculty_name'
            )
            ->join('users u', 'u.id = s.user_id')
            ->leftJoin('departments d', 'd.id = s.department_id')
            ->leftJoin('faculties f', 'f.id = d.faculty_id')
            ->whereNull('u.deleted_at');
    }

    public function profile(int $staffId): ?array
    {
        return $this->listing()->where('s.id', $staffId)->first();
    }

    public function byUserId(int $userId): ?array
    {
        return $this->listing()->where('s.user_id', $userId)->first();
    }

    public function fullName(array $staff): string
    {
        return trim(implode(' ', array_filter([
            $staff['title'] ?? null,
            $staff['first_name'] ?? null,
            $staff['last_name'] ?? null,
        ])));
    }

    public function nextStaffNumber(): string
    {
        $prefix = (string) \App\Core\Setting::get('institution_short_name', 'UNI');
        $year   = date('Y');
        $count  = (int) Database::scalar('SELECT COUNT(*) FROM staff');
        do {
            $count++;
            $number = sprintf('%s/STF/%04d/%s', $prefix, $count, $year);
        } while ($this->exists('staff_number', $number));
        return $number;
    }

    /** Course offerings taught by this member of staff. */
    public function teachingLoad(int $staffId, ?int $semesterId = null): array
    {
        $sql = 'SELECT o.*, c.code, c.title, c.credit_hours, sem.name AS semester_name,
                       ay.name AS academic_year, r.name AS room_name,
                       (SELECT COUNT(*) FROM course_registrations cr
                         WHERE cr.offering_id = o.id AND cr.status = "registered") AS students_count
                  FROM course_offerings o
                  JOIN courses c         ON c.id = o.course_id
                  JOIN semesters sem     ON sem.id = o.semester_id
                  JOIN academic_years ay ON ay.id = sem.academic_year_id
             LEFT JOIN rooms r           ON r.id = o.room_id
                 WHERE (o.lecturer_id = ?
                        OR EXISTS (SELECT 1 FROM offering_lecturers ol
                                    WHERE ol.offering_id = o.id AND ol.staff_id = ?))';
        $params = [$staffId, $staffId];
        if ($semesterId !== null) {
            $sql     .= ' AND o.semester_id = ?';
            $params[] = $semesterId;
        }
        $sql .= ' ORDER BY ay.start_date DESC, sem.semester_number DESC, c.code';
        return Database::select($sql, $params);
    }

    public function lecturerOptions(): array
    {
        $rows = Database::select(
            "SELECT s.id, CONCAT(COALESCE(u.title, ''), ' ', u.first_name, ' ', u.last_name,
                    ' (', s.staff_number, ')') AS label
               FROM staff s
               JOIN users u ON u.id = s.user_id
              WHERE s.status = 'active' AND s.staff_category = 'academic'
              ORDER BY u.last_name, u.first_name"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['id']] = trim(preg_replace('/\s+/', ' ', $row['label']));
        }
        return $out;
    }
}
