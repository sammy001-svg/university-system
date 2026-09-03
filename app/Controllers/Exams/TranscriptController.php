<?php
declare(strict_types=1);

namespace App\Controllers\Exams;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Student;

final class TranscriptController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('results.view');
        $students = Database::select(
            'SELECT s.id, s.admission_number, u.first_name, u.last_name, p.name AS program_name, s.cgpa
               FROM students s
               JOIN users u    ON u.id = s.user_id
               JOIN programs p ON p.id = s.program_id
              ORDER BY u.last_name, u.first_name'
        );
        return $this->view('exams.transcripts.index', ['pageTitle' => 'Transcripts', 'students' => $students]);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('results.view');
        $student = (new Student())->profile((int)$id);
        if (!$student) { throw new HttpException(404, 'Student not found.'); }

        $results = Database::select(
            'SELECT cr.*, c.code, c.title, c.credit_hours,
                    sem.name AS semester_name, sem.semester_number, ay.name AS academic_year
               FROM course_results cr
               JOIN course_offerings o ON o.id = cr.offering_id
               JOIN courses c          ON c.id = o.course_id
               JOIN semesters sem      ON sem.id = cr.semester_id
               JOIN academic_years ay  ON ay.id = sem.academic_year_id
              WHERE cr.student_id = ? AND cr.is_published = 1
              ORDER BY ay.start_date, sem.semester_number, c.code',
            [(int)$id]
        );

        return $this->view('exams.transcripts.show', [
            'pageTitle' => 'Transcript – ' . $student['first_name'] . ' ' . $student['last_name'],
            'student'   => $student,
            'results'   => $results,
            'history'   => (new Student())->academicHistory((int)$id),
        ]);
    }
}
