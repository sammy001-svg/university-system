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

        $data['semester_id']      = $semester['id'];
        $data['registration_type']= 'normal';
        $data['approval_status']  = 'approved';
        $data['status']           = 'registered';
        (new CourseRegistration())->create($data);
        $this->success('Registration created.', '/registrations');
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
        Database::statement("UPDATE course_registrations SET status='dropped' WHERE id=?", [(int)$id]);
        $this->success('Registration dropped.', '/registrations');
    }
}
