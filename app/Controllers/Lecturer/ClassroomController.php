<?php
declare(strict_types=1);

namespace App\Controllers\Lecturer;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\Upload;
use App\Services\Elearning\ClassroomService;
use App\Services\Elearning\CourseAccess;
use RuntimeException;

/** A lecturer's online classroom for one class: live sessions and course materials. */
final class ClassroomController extends Controller
{
    public function show(Request $request, string $id): string
    {
        $this->authorize('teaching.access');
        $offering = CourseAccess::teaching((int) $id);

        $sessions  = ClassroomService::sessions([(int) $id]);
        $materials = Database::select(
            'SELECT * FROM course_materials WHERE offering_id = ? ORDER BY COALESCE(week, 999), created_at',
            [(int) $id]
        );
        $exams = Database::select(
            'SELECT e.*, (SELECT COUNT(*) FROM online_exam_attempts t WHERE t.exam_id = e.id) AS attempts
               FROM online_exams e WHERE e.offering_id = ? ORDER BY e.opens_at',
            [(int) $id]
        );
        $assignments = Database::select(
            'SELECT a.*, (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) AS submitted,
                    (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id AND sub.graded_at IS NULL) AS to_mark
               FROM assignments a WHERE a.offering_id = ? ORDER BY a.due_at',
            [(int) $id]
        );
        $students = (int) Database::scalar(
            'SELECT COUNT(*) FROM course_registrations WHERE offering_id = ? AND status <> "dropped"',
            [(int) $id]
        );

        return $this->view('lecturer.classroom.show', [
            'pageTitle' => 'Online Classroom - ' . $offering['code'],
            'offering'  => $offering,
            'sessions'  => $sessions,
            'materials' => $materials,
            'exams'     => $exams,
            'assignments' => $assignments,
            'students'  => $students,
            'editing'   => $request->filled('edit')
                ? Database::selectOne('SELECT * FROM class_sessions WHERE id = ? AND offering_id = ?', [(int) $request->query('edit'), (int) $id])
                : null,
        ]);
    }

    /* ---------------- Live sessions ---------------- */

    public function storeSession(Request $request, string $id): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $offering = CourseAccess::teaching((int) $id);
        $data     = $this->sessionData($request, $offering);

        ClassroomService::createSession($offering, $data, (int) Auth::id());
        $this->success('Online class scheduled. Students on the class list can join from their portal.', '/teaching/' . $id . '/classroom');
    }

    public function updateSession(Request $request, string $sessionId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $session  = $this->session((int) $sessionId);
        $offering = CourseAccess::teaching((int) $session['offering_id']);

        $action = (string) $request->input('action', 'save');
        if ($action === 'cancel' || $action === 'complete') {
            ClassroomService::setSessionStatus($session, $offering, $action);
            $this->success($action === 'cancel' ? 'Class cancelled; students have been notified.' : 'Class marked as held.',
                '/teaching/' . $session['offering_id'] . '/classroom');
        }

        $data = $this->sessionData($request, $offering, $session, true);
        ClassroomService::updateSession($session, $data);
        $this->success('Online class updated.', '/teaching/' . $session['offering_id'] . '/classroom');
    }

    public function destroySession(Request $request, string $sessionId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $session = $this->session((int) $sessionId);
        CourseAccess::teaching((int) $session['offering_id']);
        ClassroomService::deleteSession($session);
        $this->success('Online class deleted.', '/teaching/' . $session['offering_id'] . '/classroom');
    }

    /** Who joined the class, against the class list. */
    public function attendance(Request $request, string $sessionId): string
    {
        $this->authorize('teaching.access');
        $session  = $this->session((int) $sessionId);
        $offering = CourseAccess::teaching((int) $session['offering_id']);
        $students = Database::select(
            'SELECT s.admission_number, u.first_name, u.last_name, a.first_joined_at, a.last_joined_at, a.join_count
               FROM course_registrations cr
               JOIN students s ON s.id = cr.student_id
               JOIN users u    ON u.id = s.user_id
          LEFT JOIN class_session_attendance a ON a.session_id = ? AND a.student_id = cr.student_id
              WHERE cr.offering_id = ? AND cr.status <> "dropped"
              ORDER BY a.first_joined_at IS NULL, u.last_name, u.first_name',
            [$session['id'], $session['offering_id']]
        );
        return $this->view('lecturer.classroom.attendance', [
            'pageTitle' => 'Online class attendance',
            'offering'  => $offering,
            'session'   => $session,
            'students'  => $students,
        ]);
    }

    /* ---------------- Materials ---------------- */

    public function storeMaterial(Request $request, string $id): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $offering = CourseAccess::teaching((int) $id);
        $back     = '/teaching/' . $id . '/classroom#materials';

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            $this->error('Give the material a title.', $back);
        }
        $week = $request->filled('week') ? max(1, min(30, (int) $request->input('week'))) : null;
        $kind = $request->input('kind') === 'link' ? 'link' : 'file';
        $file = ['path' => null, 'name' => null, 'size' => null];
        $link = null;
        try {
            if ($kind === 'link') {
                $link = ClassroomService::optionalUrl((string) $request->input('url', ''), 'Link');
                if ($link === null) {
                    throw new RuntimeException('Enter the link (starting with https://).');
                }
            } else {
                $upload = $request->file('file');
                if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Choose a file to upload.');
                }
                $file = Upload::storePrivate($upload, 'materials/' . (int) $id, array_merge(Upload::DOCUMENTS, Upload::IMAGES, Upload::ARCHIVES), 25);
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), $back);
        }

        Database::statement(
            'INSERT INTO course_materials (offering_id, title, description, week, kind, file_path, file_name, file_size, url, is_published, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, $title, trim((string) $request->input('description', '')) ?: null, $week, $kind,
             $file['path'], $file['name'], $file['size'], $link, $request->input('is_published', '1') === '1' ? 1 : 0, Auth::id()]
        );
        if ($request->input('is_published', '1') === '1') {
            ClassroomService::notifyClass((int) $id, 'New course material: ' . $offering['code'], $title, '/portal/courses/' . $id);
        }
        $this->success('Material shared with the class.', $back);
    }

    public function destroyMaterial(Request $request, string $materialId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $material = Database::selectOne('SELECT * FROM course_materials WHERE id = ?', [(int) $materialId]);
        if ($material === null) {
            throw new HttpException(404);
        }
        CourseAccess::teaching((int) $material['offering_id']);
        Database::statement('DELETE FROM course_materials WHERE id = ?', [$material['id']]);
        Upload::delete($material['file_path']);
        $this->success('Material removed.', '/teaching/' . $material['offering_id'] . '/classroom#materials');
    }

    /* ---------------- Helpers ---------------- */

    private function session(int $id): array
    {
        $session = Database::selectOne('SELECT * FROM class_sessions WHERE id = ?', [$id]);
        if ($session === null) {
            throw new HttpException(404, 'Online class not found.');
        }
        return $session;
    }

    private function sessionData(Request $request, array $offering, ?array $existing = null, bool $withRecording = false): array
    {
        $back = '/teaching/' . $offering['id'] . '/classroom' . ($existing ? '?edit=' . $existing['id'] : '');
        $data = $this->validate($request, ClassroomService::SESSION_RULES, ClassroomService::SESSION_LABELS);
        if ($withRecording) {
            $data['recording_url'] = (string) $request->input('recording_url', '');
        }
        try {
            return ClassroomService::normalizeSessionInput($data, $offering, $existing);
        } catch (RuntimeException $e) {
            Session::keepOldInput($request->all());
            $this->error($e->getMessage(), $back);
        }
    }
}
