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
use App\Models\SemesterEnrollment;
use App\Models\Student;
use App\Services\BillingService;

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

        $enrollments = new SemesterEnrollment();

        return $this->view('portal.registration', [
            'pageTitle'  => 'Course Registration',
            'student'    => $student,
            'semester'   => $semester,
            'registered' => $registered,
            'available'  => $available,
            'enrollment' => $semester ? $enrollments->forStudent((int) $student['id'], (int) $semester['id']) : null,
            'locked'     => $semester ? $enrollments->changesLocked((int) $student['id'], (int) $semester['id']) : null,
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

        $locked = (new SemesterEnrollment())->changesLocked((int) $student['id'], (int) $semester['id']);
        if ($locked !== null) {
            $this->error($locked, '/portal/registration');
        }

        $data = $this->validate($request, [
            'offering_id' => 'required|integer|exists:course_offerings,id',
        ]);

        $existing = Database::selectOne(
            'SELECT id, status FROM course_registrations WHERE student_id = ? AND offering_id = ?',
            [$student['id'], $data['offering_id']]
        );
        if ($existing && $existing['status'] !== 'dropped') {
            $this->error('You are already registered for this course.', '/portal/registration');
        }

        $registrations = new CourseRegistration();
        if ($existing) {
            $registrations->update((int) $existing['id'], ['status' => 'registered', 'approval_status' => 'pending']);
        } else {
            $registrations->create([
                'student_id'        => $student['id'],
                'offering_id'       => $data['offering_id'],
                'semester_id'       => $semester['id'],
                'registration_type' => 'normal',
                'approval_status'   => 'pending',
                'status'            => 'registered',
            ]);
        }

        // The semester bill follows the courses: tuition is charged per credit hour.
        (new BillingService())->refreshSemesterBill((int) $student['id'], (int) $semester['id']);
        $this->success('Course added and your bill updated. Pay the 1st installment to complete registration.', '/portal/registration');
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
        $locked = (new SemesterEnrollment())->changesLocked((int) $student['id'], (int) $reg['semester_id']);
        if ($locked !== null) {
            $this->error($locked, '/portal/registration');
        }

        (new CourseRegistration())->update((int) $id, ['status' => 'dropped']);
        (new BillingService())->refreshSemesterBill((int) $student['id'], (int) $reg['semester_id']);
        $this->success('Course dropped and your bill updated.', '/portal/registration');
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
