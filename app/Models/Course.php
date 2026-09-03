<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Course extends Model
{
    protected string $table = 'courses';
    protected array $fillable = [
        'department_id', 'code', 'title', 'credit_hours', 'lecture_hours', 'tutorial_hours',
        'practical_hours', 'level', 'description', 'objectives', 'status',
    ];
    protected array $searchable = ['code', 'title'];

    public function listing(): QueryBuilder
    {
        return $this->query('c')
            ->select('c.*', 'd.name AS department_name', 'd.code AS department_code', 'f.name AS faculty_name')
            ->join('departments d', 'd.id = c.department_id')
            ->join('faculties f', 'f.id = d.faculty_id');
    }

    public function options(string $labelColumn = 'title', ?string $orderBy = null, array $where = []): array
    {
        $rows = Database::select(
            "SELECT id, CONCAT(code, ' - ', title) AS label FROM courses WHERE status = 'active' ORDER BY code"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['id']] = $row['label'];
        }
        return $out;
    }

    public function prerequisites(int $courseId): array
    {
        return Database::select(
            'SELECT cp.id, c.id AS course_id, c.code, c.title
               FROM course_prerequisites cp
               JOIN courses c ON c.id = cp.prerequisite_course_id
              WHERE cp.course_id = ?
              ORDER BY c.code',
            [$courseId]
        );
    }

    /** Programs whose curriculum includes this course. */
    public function programs(int $courseId): array
    {
        return Database::select(
            'SELECT p.id, p.code, p.name, pc.year_of_study, pc.semester_number, pc.course_type
               FROM program_courses pc
               JOIN programs p ON p.id = pc.program_id
              WHERE pc.course_id = ?
              ORDER BY p.name',
            [$courseId]
        );
    }
}
