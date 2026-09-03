<?php
declare(strict_types=1);

namespace App\Controllers\Attendance;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\AttendanceSession;

final class AttendanceController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('attendance.view');
        $sessions = Database::select(
            'SELECT s.*, c.code, c.title, o.section
               FROM attendance_sessions s
               JOIN course_offerings o ON o.id = s.offering_id
               JOIN courses c          ON c.id = o.course_id
              ORDER BY s.session_date DESC, s.start_time DESC
              LIMIT 100'
        );
        return $this->view('attendance.index', ['pageTitle' => 'Attendance', 'sessions' => $sessions]);
    }

    public function create(Request $request): string
    {
        $this->authorize('attendance.create');
        $offerings = Database::select(
            'SELECT o.id, c.code, c.title, o.section, sem.name AS semester_name
               FROM course_offerings o
               JOIN courses c     ON c.id = o.course_id
               JOIN semesters sem ON sem.id = o.semester_id
              WHERE sem.is_current = 1 AND o.status = "open"
              ORDER BY c.code'
        );
        return $this->view('attendance.create', ['pageTitle' => 'New Attendance Session', 'offerings' => $offerings]);
    }

    public function store(Request $request): never
    {
        $this->authorize('attendance.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'offering_id'  => 'required|integer|exists:course_offerings,id',
            'session_date' => 'required|date',
            'start_time'   => 'nullable',
            'end_time'     => 'nullable',
            'topic'        => 'nullable|max:255',
            'session_type' => 'required|in:lecture,tutorial,practical,seminar',
        ]);
        $data['taken_by'] = Auth::id();
        $id = (new AttendanceSession())->create($data);
        $this->success('Session created.', '/attendance/sessions/' . $id);
    }

    public function mark(Request $request, string $id): string
    {
        $this->authorize('attendance.edit');
        $session = $this->session((int) $id);
        $students = Database::select(
            'SELECT cr.student_id, s.admission_number, u.first_name, u.last_name,
                    ar.id AS record_id, ar.status AS att_status, ar.remarks
               FROM course_registrations cr
               JOIN students s ON s.id = cr.student_id
               JOIN users u    ON u.id = s.user_id
          LEFT JOIN attendance_records ar ON ar.session_id = ? AND ar.student_id = cr.student_id
              WHERE cr.offering_id = ? AND cr.status = "registered"
              ORDER BY u.last_name, u.first_name',
            [(int) $id, $session['offering_id']]
        );
        return $this->view('attendance.mark', [
            'pageTitle' => 'Mark Attendance',
            'session'   => $session,
            'students'  => $students,
        ]);
    }

    public function save(Request $request, string $id): never
    {
        $this->authorize('attendance.edit');
        $this->verifyCsrf($request);
        $session  = $this->session((int) $id);
        $statuses = $request->input('attendance', []);

        foreach ((array) $statuses as $studentId => $status) {
            $valid = in_array($status, ['present','absent','late','excused'], true) ? $status : 'absent';
            $existing = Database::scalar('SELECT id FROM attendance_records WHERE session_id=? AND student_id=?', [(int)$id, (int)$studentId]);
            if ($existing) {
                Database::statement('UPDATE attendance_records SET status=?, marked_by=? WHERE id=?', [$valid, Auth::id(), (int)$existing]);
            } else {
                Database::statement('INSERT INTO attendance_records (session_id, student_id, status, marked_by) VALUES (?,?,?,?)', [(int)$id, (int)$studentId, $valid, Auth::id()]);
            }
        }
        Database::statement("UPDATE attendance_sessions SET status='closed' WHERE id=?", [(int)$id]);
        $this->success('Attendance saved.', '/attendance');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('attendance.delete');
        $this->verifyCsrf($request);
        (new AttendanceSession())->delete((int) $id);
        $this->success('Session deleted.', '/attendance');
    }

    public function report(Request $request): string
    {
        $this->authorize('attendance.view');
        return $this->view('attendance.report', ['pageTitle' => 'Attendance Report']);
    }

    private function session(int $id): array
    {
        $row = Database::selectOne('SELECT * FROM attendance_sessions WHERE id=?', [$id]);
        if (!$row) { throw new HttpException(404); }
        return $row;
    }
}
