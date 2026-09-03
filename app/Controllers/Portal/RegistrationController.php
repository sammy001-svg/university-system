<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;

final class RegistrationController extends Controller
{
    public function index(Request $request): string
    {
        $student  = $this->student();
        $semester = (new Semester())->current();

        $registered = (new Student())->registrations($student['id'], $semester['id'] ?? null);

        // Available offerings not yet registered
        $available = $semester ? Database::select(
            'SELECT o.id AS offering_id, c.code, c.title, c.credit_hours, o.section,
                    o.enrolled_count, o.capacity, o.delivery_mode,
                    CONCAT(lu.first_name, " ", lu.last_name) AS lecturer_name
               FROM course_offerings o
               JOIN courses c ON c.id = o.course_id
          LEFT JOIN staff lst ON lst.id = o.lecturer_id
          LEFT JOIN users lu  ON lu.id = lst.user_id
              WHERE o.semester_id = ? AND o.status = "open"
                AND o.id NOT IN (
                    SELECT offering_id FROM course_registrations
                     WHERE student_id = ? AND status != "dropped"
                )
              ORDER BY c.code',
            [$semester['id'], $student['id']]
        ) : [];

        return $this->view('portal.registration', [
            'pageTitle'  => 'Course Registration',
            'student'    => $student,
            'semester'   => $semester,
            'registered' => $registered,
            'available'  => $available,
        ]);
    }

    public function register(Request $request): never
    {
        $this->verifyCsrf($request);
        $student  = $this->student();
        $semester = (new Semester())->current();

        if (!$semester) {
            $this->error('No active semester for registration.', '/portal/registration');
        }

        $data = $this->validate($request, [
            'offering_id' => 'required|integer|exists:course_offerings,id',
        ]);

        $existing = Database::scalar(
            'SELECT id FROM course_registrations WHERE student_id = ? AND offering_id = ? AND status != "dropped"',
            [$student['id'], $data['offering_id']]
        );
        if ($existing) {
            $this->error('You are already registered for this course.', '/portal/registration');
        }

        (new CourseRegistration())->create([
            'student_id'        => $student['id'],
            'offering_id'       => $data['offering_id'],
            'semester_id'       => $semester['id'],
            'registration_type' => 'normal',
            'approval_status'   => 'pending',
            'status'            => 'registered',
        ]);

        $this->success('Course registered successfully. Awaiting approval.', '/portal/registration');
    }

    public function drop(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        $student = $this->student();

        $reg = Database::selectOne(
            'SELECT * FROM course_registrations WHERE id = ? AND student_id = ?',
            [(int) $id, $student['id']]
        );
        if (!$reg) {
            throw new HttpException(404);
        }

        (new CourseRegistration())->update((int) $id, ['status' => 'dropped']);
        $this->success('Course dropped.', '/portal/registration');
    }

    private function student(): array
    {
        $student = (new Student())->byUserId((int) Auth::id());
        if (!$student) {
            throw new HttpException(403, 'No student profile linked to your account.');
        }
        return $student;
    }
}
