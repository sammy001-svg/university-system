<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\FeeStructure;
use App\Models\Semester;
use App\Models\SemesterEnrollment;
use App\Models\Student;

/** The parent / sponsor portal: a read-only view of each linked student, and paying their fees. */
final class FamilyController extends Controller
{
    public function index(Request $request): string
    {
        $students = $this->linked();
        if (count($students) === 1) {
            $this->redirect('/family/' . $students[0]['id']);
        }
        return $this->view('family.index', ['pageTitle' => 'My students', 'students' => $students]);
    }

    public function show(Request $request, string $id): string
    {
        $student  = $this->student((int) $id);
        $semester = (new Semester())->current();
        $model    = new Student();
        $gpa = Database::selectOne(
            'SELECT SUM(quality_points) AS qp, SUM(credit_hours) AS ch FROM course_results WHERE student_id = ? AND is_published = 1',
            [$student['id']]
        );
        return $this->view('family.show', [
            'pageTitle'     => $student['first_name'] . ' ' . $student['last_name'],
            'student'       => $student,
            'others'        => $this->linked(),
            'semester'      => $semester,
            'standing'      => FeeStructure::classStanding($student),
            'enrollment'    => $semester ? (new SemesterEnrollment())->forStudent((int) $student['id'], (int) $semester['id']) : null,
            'registrations' => $model->registrations((int) $student['id'], $semester['id'] ?? null),
            'balance'       => $model->balance((int) $student['id']),
            'attendance'    => $model->attendanceRate((int) $student['id'], $semester['id'] ?? null),
            'cgpa'          => (float) ($gpa['ch'] ?? 0) > 0 ? round((float) $gpa['qp'] / (float) $gpa['ch'], 2) : null,
            'results'       => Database::select(
                'SELECT r.grade, r.total_score, r.outcome, r.credit_hours, c.code, c.title, sem.name AS semester_name, ay.name AS academic_year
                   FROM course_results r JOIN courses c ON c.id = r.course_id JOIN semesters sem ON sem.id = r.semester_id
                   JOIN academic_years ay ON ay.id = sem.academic_year_id
                  WHERE r.student_id = ? AND r.is_published = 1 ORDER BY ay.start_date DESC, sem.semester_number DESC, c.code',
                [$student['id']]
            ),
        ]);
    }

    public function fees(Request $request, string $id): string
    {
        $student = $this->student((int) $id);
        return $this->view('family.fees', [
            'pageTitle' => 'Fees - ' . $student['first_name'] . ' ' . $student['last_name'],
            'student'   => $student,
            'invoices'  => Database::select('SELECT * FROM invoices WHERE student_id = ? AND status NOT IN ("draft") ORDER BY created_at DESC', [$student['id']]),
            'payments'  => Database::select('SELECT * FROM payments WHERE student_id = ? AND status = "confirmed" ORDER BY paid_at DESC', [$student['id']]),
            'online'    => Database::select('SELECT * FROM online_payments WHERE student_id = ? ORDER BY id DESC LIMIT 10', [$student['id']]),
            'balance'   => (new Student())->balance((int) $student['id']),
        ]);
    }

    /** Students linked to the signed-in parent. */
    private function linked(): array
    {
        if (Auth::user()['user_type'] !== 'parent') {
            throw new HttpException(403, 'The family portal is for parents and sponsors.');
        }
        return Database::select(
            'SELECT s.id, s.admission_number, s.year_of_study, s.current_semester, s.status, u.first_name, u.last_name, u.avatar,
                    p.name AS program_name, g.relationship
               FROM guardians g JOIN students s ON s.id = g.student_id JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
              WHERE g.user_id = ? ORDER BY u.first_name',
            [Auth::id()]
        );
    }

    private function student(int $id): array
    {
        foreach ($this->linked() as $s) {
            if ((int) $s['id'] === $id) {
                return (new Student())->profile($id) + ['relationship' => $s['relationship']];
            }
        }
        throw new HttpException(404, 'This student is not linked to your account.');
    }
}
