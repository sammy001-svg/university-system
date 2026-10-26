<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Program extends Model
{
    protected string $table = 'programs';
    protected array $fillable = [
        'department_id', 'code', 'name', 'award', 'level', 'duration_years', 'semesters_per_year',
        'total_credit_hours', 'study_mode', 'min_entry_grade', 'entry_requirements',
        'description', 'application_fee', 'status',
    ];
    protected array $searchable = ['code', 'name', 'award'];

    public const LEVELS = ['certificate', 'diploma', 'associate', 'bachelor', 'postgraduate_diploma', 'masters', 'phd'];
    public const MODES  = ['full_time', 'part_time', 'evening', 'distance'];

    public function listing(): QueryBuilder
    {
        return $this->query('p')
            ->select(
                'p.*', 'd.name AS department_name', 'd.code AS department_code',
                'f.name AS faculty_name',
                '(SELECT COUNT(*) FROM students st WHERE st.program_id = p.id AND st.status = "active") AS students_count'
            )
            ->join('departments d', 'd.id = p.department_id')
            ->join('faculties f', 'f.id = d.faculty_id');
    }

    public function withDepartment(int $id): ?array
    {
        return Database::selectOne(
            'SELECT p.*, d.name AS department_name, f.name AS faculty_name, f.id AS faculty_id
               FROM programs p
               JOIN departments d ON d.id = p.department_id
               JOIN faculties f   ON f.id = d.faculty_id
              WHERE p.id = ?',
            [$id]
        );
    }

    public function options(string $labelColumn = 'name', ?string $orderBy = null, array $where = []): array
    {
        $rows = Database::select(
            "SELECT id, CONCAT(code, ' - ', name) AS label FROM programs WHERE status = 'active' ORDER BY name"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['id']] = $row['label'];
        }
        return $out;
    }

    /**
     * The curriculum grouped by year and semester. Placeholder slots (minor,
     * elective, content area) have no course; credit_hours is the programme's own count.
     */
    public function curriculum(int $programId): array
    {
        $rows = Database::select(
            'SELECT pc.*, c.code, c.title, c.status AS course_status, d.name AS department_name,
                    (SELECT GROUP_CONCAT(a.code ORDER BY a.code SEPARATOR ", ") FROM course_aliases a
                      WHERE a.course_id = c.id AND a.code != c.code) AS billing_codes
               FROM program_courses pc
          LEFT JOIN courses c     ON c.id = pc.course_id
          LEFT JOIN departments d ON d.id = c.department_id
              WHERE pc.program_id = ?
              ORDER BY pc.year_of_study, pc.semester_number, pc.sort_order, c.code',
            [$programId]
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['year_of_study']][(int) $row['semester_number']][] = $row;
        }
        return $grouped;
    }

    /** Credits for a list of plan rows, counting one alternative per either/or group. */
    public static function termCredits(array $rows): int
    {
        $total  = 0;
        $groups = [];
        foreach ($rows as $row) {
            if ($row['choice_group'] === null) {
                $total += (int) $row['credit_hours'];
            } else {
                $groups[$row['choice_group']] = min($groups[$row['choice_group']] ?? PHP_INT_MAX, (int) $row['credit_hours']);
            }
        }
        return $total + array_sum($groups);
    }

    public function totalCredits(int $programId): int
    {
        $total = 0;
        foreach ($this->curriculum($programId) as $semesters) {
            foreach ($semesters as $rows) {
                $total += self::termCredits($rows);
            }
        }
        return $total;
    }

    /** Electives, minor requirements and concentrations, grouped by list then heading. */
    public function courseOptions(int $programId): array
    {
        $rows = Database::select(
            'SELECT o.*, c.code, c.title
               FROM program_course_options o
          LEFT JOIN courses c ON c.id = o.course_id
              WHERE o.program_id = ?
              ORDER BY FIELD(o.list_type, "elective", "concentration", "minor"), o.group_label, o.sort_order',
            [$programId]
        );
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['list_type']][(string) $row['group_label']][] = $row;
        }
        return $grouped;
    }
}
