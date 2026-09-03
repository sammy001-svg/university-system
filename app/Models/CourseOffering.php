<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class CourseOffering extends Model
{
    protected string $table = 'course_offerings';
    protected array $fillable = [
        'course_id', 'semester_id', 'program_id', 'section', 'lecturer_id', 'room_id', 'capacity',
        'delivery_mode', 'coursework_weight', 'exam_weight', 'status',
    ];
    protected array $searchable = ['c.code', 'c.title'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('course_offerings', 'o')
            ->select(
                'o.*', 'c.code', 'c.title', 'c.credit_hours', 'c.department_id',
                'sem.name AS semester_name', 'ay.name AS academic_year',
                'r.name AS room_name', 'p.name AS program_name',
                "CONCAT(COALESCE(u.title,''), ' ', u.first_name, ' ', u.last_name) AS lecturer_name",
                '(SELECT COUNT(*) FROM course_registrations cr WHERE cr.offering_id = o.id AND cr.status = "registered") AS registered_count'
            )
            ->join('courses c', 'c.id = o.course_id')
            ->join('semesters sem', 'sem.id = o.semester_id')
            ->join('academic_years ay', 'ay.id = sem.academic_year_id')
            ->leftJoin('rooms r', 'r.id = o.room_id')
            ->leftJoin('programs p', 'p.id = o.program_id')
            ->leftJoin('staff st', 'st.id = o.lecturer_id')
            ->leftJoin('users u', 'u.id = st.user_id');
    }

    public function detail(int $offeringId): ?array
    {
        return $this->listing()->where('o.id', $offeringId)->first();
    }

    /** Students registered on an offering. */
    public function roster(int $offeringId): array
    {
        return Database::select(
            'SELECT cr.id AS registration_id, cr.registration_type, cr.approval_status,
                    s.id AS student_id, s.admission_number, s.year_of_study,
                    u.first_name, u.last_name, u.email, u.avatar,
                    p.code AS program_code,
                    res.coursework_score, res.exam_score, res.total_score, res.grade, res.outcome, res.is_published
               FROM course_registrations cr
               JOIN students s  ON s.id = cr.student_id
               JOIN users u     ON u.id = s.user_id
               JOIN programs p  ON p.id = s.program_id
          LEFT JOIN course_results res ON res.student_id = s.id AND res.offering_id = cr.offering_id
              WHERE cr.offering_id = ? AND cr.status = "registered"
              ORDER BY u.last_name, u.first_name',
            [$offeringId]
        );
    }

    public function refreshEnrolledCount(int $offeringId): void
    {
        Database::statement(
            'UPDATE course_offerings SET enrolled_count =
               (SELECT COUNT(*) FROM course_registrations
                 WHERE offering_id = ? AND status = "registered")
             WHERE id = ?',
            [$offeringId, $offeringId]
        );
    }

    public function hasCapacity(int $offeringId): bool
    {
        $row = Database::selectOne(
            'SELECT o.capacity,
                    (SELECT COUNT(*) FROM course_registrations cr
                      WHERE cr.offering_id = o.id AND cr.status = "registered") AS taken
               FROM course_offerings o WHERE o.id = ?',
            [$offeringId]
        );
        if ($row === null) {
            return false;
        }
        return (int) $row['taken'] < (int) $row['capacity'];
    }

    /** Offerings a student may register for in a semester, from their curriculum. */
    public function availableFor(int $studentId, int $semesterId): array
    {
        return Database::select(
            'SELECT o.id, o.section, o.capacity, o.delivery_mode, o.status,
                    c.code, c.title, c.credit_hours, pc.course_type, pc.year_of_study, pc.semester_number,
                    r.name AS room_name,
                    CONCAT(u.first_name, " ", u.last_name) AS lecturer_name,
                    (SELECT COUNT(*) FROM course_registrations cr2
                      WHERE cr2.offering_id = o.id AND cr2.status = "registered") AS registered_count,
                    (SELECT COUNT(*) FROM course_registrations cr3
                      WHERE cr3.offering_id = o.id AND cr3.student_id = ?) AS already_registered
               FROM course_offerings o
               JOIN courses c        ON c.id = o.course_id
               JOIN program_courses pc ON pc.course_id = c.id
               JOIN students s       ON s.id = ? AND s.program_id = pc.program_id
          LEFT JOIN rooms r          ON r.id = o.room_id
          LEFT JOIN staff st         ON st.id = o.lecturer_id
          LEFT JOIN users u          ON u.id = st.user_id
              WHERE o.semester_id = ? AND o.status = "open"
              ORDER BY pc.year_of_study, pc.semester_number, c.code',
            [$studentId, $studentId, $semesterId]
        );
    }
}
