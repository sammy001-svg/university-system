<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Student;

final class ResultController extends Controller
{
    public function index(Request $request): string
    {
        $student  = $this->student();
        $history  = (new Student())->academicHistory($student['id']);

        $results = Database::select(
            'SELECT cr.*, c.code, c.title, c.credit_hours,
                    sem.name AS semester_name, ay.name AS academic_year
               FROM course_results cr
               JOIN course_offerings o ON o.id = cr.offering_id
               JOIN courses c          ON c.id = cr.offering_id
               JOIN semesters sem      ON sem.id = cr.semester_id
               JOIN academic_years ay  ON ay.id = sem.academic_year_id
              WHERE cr.student_id = ? AND cr.is_published = 1
              ORDER BY ay.start_date DESC, sem.semester_number DESC, c.code',
            [$student['id']]
        );

        return $this->view('portal.results', [
            'pageTitle' => 'My Results',
            'student'   => $student,
            'results'   => $results,
            'history'   => $history,
        ]);
    }

    public function transcript(Request $request): string
    {
        $student = $this->student();
        $results = Database::select(
            'SELECT cr.*, c.code, c.title, c.credit_hours,
                    sem.name AS semester_name, ay.name AS academic_year, sem.semester_number
               FROM course_results cr
               JOIN course_offerings o ON o.id = cr.offering_id
               JOIN courses c          ON c.id = cr.offering_id
               JOIN semesters sem      ON sem.id = cr.semester_id
               JOIN academic_years ay  ON ay.id = sem.academic_year_id
              WHERE cr.student_id = ? AND cr.is_published = 1
              ORDER BY ay.start_date, sem.semester_number, c.code',
            [$student['id']]
        );

        return $this->view('portal.transcript', [
            'pageTitle' => 'Academic Transcript',
            'student'   => $student,
            'results'   => $results,
            'history'   => (new Student())->academicHistory($student['id']),
        ]);
    }

    private function student(): array
    {
        $s = (new Student())->byUserId((int) Auth::id());
        if (!$s) { throw new HttpException(403, 'No student profile linked to your account.'); }
        return $s;
    }
}
