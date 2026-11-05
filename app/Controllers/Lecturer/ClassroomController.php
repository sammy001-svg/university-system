<?php
declare(strict_types=1);

namespace App\Controllers\Lecturer;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Notify;
use App\Core\Request;
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

        Database::statement(
            'INSERT INTO class_sessions (offering_id, title, description, platform, meeting_url, meeting_id, passcode,
                                         starts_at, duration_minutes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, $data['title'], $data['description'], $data['platform'], $data['meeting_url'], $data['meeting_id'],
             $data['passcode'], $data['starts_at'], $data['duration_minutes'], Auth::id()]
        );
        $sessionId = Database::lastInsertId();
        Audit::log('create', 'elearning', 'class_sessions', (string) $sessionId, "Scheduled online class {$data['title']} for {$offering['code']}");
        $this->notifyClass((int) $id, 'Online class scheduled: ' . $offering['code'],
            $data['title'] . ' on ' . fdatetime($data['starts_at']) . '.', '/portal/classes');

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
            Database::statement('UPDATE class_sessions SET status = ? WHERE id = ?', [$action === 'cancel' ? 'cancelled' : 'completed', $session['id']]);
            if ($action === 'cancel') {
                $this->notifyClass((int) $session['offering_id'], 'Online class cancelled: ' . $offering['code'],
                    $session['title'] . ' (' . fdatetime($session['starts_at']) . ') has been cancelled.', '/portal/classes');
            }
            $this->success($action === 'cancel' ? 'Class cancelled; students have been notified.' : 'Class marked as held.',
                '/teaching/' . $session['offering_id'] . '/classroom');
        }

        $data = $this->sessionData($request, $offering, $session);
        try {
            $data['recording_url'] = $this->optionalUrl((string) $request->input('recording_url', ''), 'Recording link');
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/teaching/' . $session['offering_id'] . '/classroom?edit=' . $session['id']);
        }
        Database::statement(
            'UPDATE class_sessions SET title = ?, description = ?, platform = ?, meeting_url = ?, meeting_id = ?, passcode = ?,
                    starts_at = ?, duration_minutes = ?, recording_url = ?, status = IF(status = "cancelled", "scheduled", status)
              WHERE id = ?',
            [$data['title'], $data['description'], $data['platform'], $data['meeting_url'], $data['meeting_id'], $data['passcode'],
             $data['starts_at'], $data['duration_minutes'], $data['recording_url'], $session['id']]
        );
        $this->success('Online class updated.', '/teaching/' . $session['offering_id'] . '/classroom');
    }

    public function destroySession(Request $request, string $sessionId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $session = $this->session((int) $sessionId);
        CourseAccess::teaching((int) $session['offering_id']);
        Database::statement('DELETE FROM class_sessions WHERE id = ?', [$session['id']]);
        Audit::log('delete', 'elearning', 'class_sessions', (string) $session['id'], 'Deleted online class ' . $session['title']);
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
                $link = $this->optionalUrl((string) $request->input('url', ''), 'Link');
                if ($link === null) {
                    throw new RuntimeException('Enter the link (starting with https://).');
                }
            } else {
                $upload = $request->file('file');
                if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    throw new RuntimeException('Choose a file to upload.');
                }
                $file = Upload::store($upload, 'materials/' . (int) $id, array_merge(Upload::DOCUMENTS, Upload::IMAGES, ['zip']), 25);
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
            $this->notifyClass((int) $id, 'New course material: ' . $offering['code'], $title, '/portal/courses/' . $id);
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
        if ($material['file_path']) {
            @unlink(dirname(__DIR__, 3) . '/public/' . $material['file_path']);
        }
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

    private function sessionData(Request $request, array $offering, ?array $existing = null): array
    {
        $back = '/teaching/' . $offering['id'] . '/classroom';
        $data = $this->validate($request, [
            'title'            => 'required|max:150',
            'platform'         => 'required|in:' . implode(',', array_keys(ClassroomService::PLATFORMS)),
            'starts_at'        => 'required',
            'duration_minutes' => 'required|integer',
            'meeting_url'      => 'nullable|max:500',
            'meeting_id'       => 'nullable|max:100',
            'passcode'         => 'nullable|max:100',
            'description'      => 'nullable',
        ], ['starts_at' => 'Date and time', 'duration_minutes' => 'Duration']);

        $start = strtotime(str_replace('T', ' ', (string) $data['starts_at']));
        if ($start === false) {
            $this->error('Enter a valid date and time.', $back);
        }
        $data['starts_at']        = date('Y-m-d H:i:s', $start);
        $data['duration_minutes'] = max(10, min(600, (int) $data['duration_minutes']));
        $data['description']      = trim((string) ($data['description'] ?? '')) ?: null;
        $data['meeting_id']       = trim((string) ($data['meeting_id'] ?? '')) ?: null;
        $data['passcode']         = trim((string) ($data['passcode'] ?? '')) ?: null;

        try {
            $url = $this->optionalUrl((string) ($data['meeting_url'] ?? ''), 'Meeting link');
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), $back);
        }
        if ($url === null) {
            if ($data['platform'] !== 'jitsi') {
                $this->error('Paste the meeting link from ' . ClassroomService::PLATFORMS[$data['platform']] . '.', $back);
            }
            // Keep the room of an existing Jitsi class so links already shared still work.
            $url = $existing !== null && $existing['platform'] === 'jitsi' ? $existing['meeting_url'] : ClassroomService::jitsiUrl($offering);
        }
        $data['meeting_url'] = $url;
        return $data;
    }

    /** null for blank; the URL when it is a valid http(s) link; an exception otherwise. */
    private function optionalUrl(string $url, string $label): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            throw new RuntimeException("{$label} must be a full web address starting with https://.");
        }
        return mb_substr($url, 0, 500);
    }

    /** Tell everyone on the class list (in-app notification). */
    private function notifyClass(int $offeringId, string $title, string $message, string $link): void
    {
        $userIds = array_column(Database::select(
            'SELECT s.user_id FROM course_registrations cr JOIN students s ON s.id = cr.student_id
              WHERE cr.offering_id = ? AND cr.status <> "dropped"',
            [$offeringId]
        ), 'user_id');
        Notify::sendMany($userIds, $title, $message, $link, 'info', 'calendar');
    }
}
