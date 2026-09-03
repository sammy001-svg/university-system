<?php
declare(strict_types=1);

namespace App\Controllers\Lecturer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;

final class TeachingController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('teaching.access');
        $staffId = $this->staffId();

        $offerings = Database::select(
            'SELECT o.*, c.code, c.title, c.credit_hours, sem.name AS semester_name,
                    ay.name AS academic_year, o.enrolled_count,
                    (SELECT COUNT(*) FROM assessments a WHERE a.offering_id = o.id) AS assessment_count
               FROM course_offerings o
               JOIN courses c       ON c.id = o.course_id
               JOIN semesters sem   ON sem.id = o.semester_id
               JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE o.lecturer_id = ? OR EXISTS (
                SELECT 1 FROM offering_lecturers ol WHERE ol.offering_id = o.id AND ol.staff_id = ?
              )
              ORDER BY ay.start_date DESC, sem.semester_number DESC, c.code',
            [$staffId, $staffId]
        );

        return $this->view('lecturer.teaching.index', [
            'pageTitle' => 'My Teaching',
            'offerings' => $offerings,
        ]);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('teaching.access');
        $offering = $this->findOffering((int) $id);

        $students = Database::select(
            'SELECT cr.*, s.admission_number, u.first_name, u.last_name, u.email,
                    res.total_score, res.grade, res.outcome
               FROM course_registrations cr
               JOIN students s     ON s.id = cr.student_id
               JOIN users u        ON u.id = s.user_id
          LEFT JOIN course_results res ON res.student_id = cr.student_id AND res.offering_id = cr.offering_id
              WHERE cr.offering_id = ? AND cr.status = "registered"
              ORDER BY u.last_name, u.first_name',
            [(int) $id]
        );

        return $this->view('lecturer.teaching.show', [
            'pageTitle' => $offering['code'] . ' – ' . $offering['title'],
            'offering'  => $offering,
            'students'  => $students,
        ]);
    }

    private function staffId(): int
    {
        $row = Database::selectOne('SELECT id FROM staff WHERE user_id = ?', [Auth::id()]);
        if (!$row) { throw new HttpException(403, 'No staff profile linked to your account.'); }
        return (int) $row['id'];
    }

    private function findOffering(int $id): array
    {
        $row = Database::selectOne(
            'SELECT o.*, c.code, c.title, c.credit_hours, sem.name AS semester_name
               FROM course_offerings o
               JOIN courses c     ON c.id = o.course_id
               JOIN semesters sem ON sem.id = o.semester_id
              WHERE o.id = ?',
            [$id]
        );
        if (!$row) { throw new HttpException(404, 'Offering not found.'); }
        return $row;
    }
}
