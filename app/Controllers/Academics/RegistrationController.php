<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Services\BillingService;

final class RegistrationController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('registrations.view');
        $query = 'SELECT cr.*, c.code, c.title, s.admission_number, u.first_name, u.last_name, sem.name AS semester_name
                    FROM course_registrations cr
                    JOIN course_offerings o ON o.id = cr.offering_id
                    JOIN courses c          ON c.id = o.course_id
                    JOIN students s         ON s.id = cr.student_id
                    JOIN users u            ON u.id = s.user_id
                    JOIN semesters sem      ON sem.id = cr.semester_id
                   WHERE 1=1';
        $params = [];
        if ($status = $request->query('approval_status')) {
            $query   .= ' AND cr.approval_status = ?';
            $params[] = $status;
        }
        $query .= ' ORDER BY cr.created_at DESC LIMIT 200';
        $registrations = Database::select($query, $params);

        return $this->view('academics.registrations.index', [
            'pageTitle'     => 'Course Registrations',
            'registrations' => $registrations,
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('registrations.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'student_id'  => 'required|integer|exists:students,id',
            'offering_id' => 'required|integer|exists:course_offerings,id',
        ]);
        $semester = (new Semester())->current();
        if (!$semester) { $this->error('No current semester.', '/registrations'); }

        $existing = Database::selectOne(
            'SELECT id, status FROM course_registrations WHERE student_id = ? AND offering_id = ?',
            [$data['student_id'], $data['offering_id']]
        );
        if ($existing && $existing['status'] !== 'dropped') { $this->error('The student is already registered for this class.', '/registrations'); }

        // Staff may add courses after registration is complete; the extra tuition falls on the later installments.
        if ($existing) {
            (new CourseRegistration())->update((int) $existing['id'], ['status' => 'registered', 'approval_status' => 'approved']);
        } else {
            $data['semester_id']      = $semester['id'];
            $data['registration_type']= 'normal';
            $data['approval_status']  = 'approved';
            $data['status']           = 'registered';
            (new CourseRegistration())->create($data);
        }
        (new BillingService())->refreshSemesterBill((int) $data['student_id'], (int) $semester['id'], (int) Auth::id());
        $this->success("Registration created and the student's bill updated.", '/registrations');
    }

    public function approve(Request $request, string $id): never
    {
        $this->authorize('registrations.approve');
        $this->verifyCsrf($request);
        Database::statement(
            'UPDATE course_registrations SET approval_status="approved", approved_by=?, approved_at=NOW() WHERE id=?',
            [Auth::id(), (int)$id]
        );
        $this->success('Registration approved.', '/registrations?approval_status=pending');
    }

    public function reject(Request $request, string $id): never
    {
        $this->authorize('registrations.approve');
        $this->verifyCsrf($request);
        Database::statement(
            'UPDATE course_registrations SET approval_status="rejected" WHERE id=?',
            [(int)$id]
        );
        $this->success('Registration rejected.', '/registrations?approval_status=pending');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('registrations.delete');
        $this->verifyCsrf($request);
        $reg = Database::selectOne('SELECT student_id, semester_id FROM course_registrations WHERE id=?', [(int)$id]);
        if (!$reg) { throw new HttpException(404); }
        Database::statement("UPDATE course_registrations SET status='dropped' WHERE id=?", [(int)$id]);
        (new BillingService())->refreshSemesterBill((int) $reg['student_id'], (int) $reg['semester_id'], (int) Auth::id());
        $this->success("Registration dropped and the student's bill updated.", '/registrations');
    }
}
