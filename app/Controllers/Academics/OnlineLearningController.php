<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Elearning\ClassroomService;
use App\Services\Elearning\CourseAccess;
use App\Services\Elearning\Oversight;
use RuntimeException;

/**
 * Online learning across the institution, for the academic office: live and
 * scheduled online classes, attendance, and how far each class has moved
 * online (sessions, materials, exams, assignments). Read-only - lecturers run
 * their own classrooms from Teaching.
 */
final class OnlineLearningController extends Controller
{
    private const PHASES = ['live' => 'Live now', 'upcoming' => 'Upcoming', 'ended' => 'Held', 'cancelled' => 'Cancelled'];

    public function index(Request $request): string
    {
        $this->authorize('elearning.view');
        $tab   = $request->query('tab') === 'coverage' ? 'coverage' : 'sessions';
        $now   = date('Y-m-d H:i:s');
        $scope = Oversight::scope($request, $tab === 'sessions' ? ['c.code', 'c.title', 's.title'] : ['c.code', 'c.title']);
        $base  = implode(' AND ', $scope['where']);

        // Headline figures cover the semester, department and search chosen, whatever the tab's own filters.
        $kpis = $this->kpis(Oversight::scope($request, ['c.code', 'c.title']), $now);

        if ($tab === 'coverage') {
            [$result, $activity] = $this->coverage($request, $base, $scope['params'], $now);
        } else {
            [$result, $phase] = $this->sessions($request, $base, $scope['params'], $now);
        }

        return $this->view('academics.online-learning.index', Oversight::lookups() + [
            'pageTitle' => 'Online Learning',
            'tab'       => $tab,
            'kpis'      => $kpis,
            'result'    => $result,
            'filters'   => $scope['filters'] + ['phase' => $phase ?? '', 'activity' => $activity ?? ''],
            'phases'    => self::PHASES,
        ]);
    }

    /** One class's online activity: sessions and who attended, materials, exams and assignments. */
    public function show(Request $request, string $id): string
    {
        $this->authorize('elearning.view');
        $offering = CourseAccess::offering((int) $id);
        if ($offering === null) {
            $this->abort(404, 'Class not found.');
        }
        $registered = (int) Database::scalar(
            'SELECT COUNT(*) FROM course_registrations WHERE offering_id = ? AND status <> "dropped"', [(int) $id]
        );
        $sessions = ClassroomService::sessions([(int) $id]);
        $held = array_filter($sessions, static fn (array $s): bool => ClassroomService::phase($s) === 'ended');
        $reached = (int) Database::scalar(
            'SELECT COUNT(DISTINCT a.student_id) FROM class_session_attendance a JOIN class_sessions s ON s.id = a.session_id WHERE s.offering_id = ?',
            [(int) $id]
        );

        return $this->view('academics.online-learning.show', [
            'pageTitle'  => 'Online Learning - ' . $offering['code'],
            'offering'   => $offering,
            'registered' => $registered,
            'sessions'   => array_reverse($sessions),
            'stats'      => [
                'held'       => count($held),
                'upcoming'   => count(array_filter($sessions, static fn (array $s): bool => in_array(ClassroomService::phase($s), ['upcoming', 'live'], true))),
                'attendance' => Oversight::pct(array_sum(array_column($held, 'attendees')), count($held) * $registered),
                'reached'    => $reached,
            ],
            'materials'  => Database::select(
                'SELECT * FROM course_materials WHERE offering_id = ? ORDER BY COALESCE(week, 999), created_at', [(int) $id]
            ),
            'exams'      => Database::select(
                'SELECT e.*, (SELECT COUNT(*) FROM online_exam_questions q WHERE q.exam_id = e.id) AS question_count,
                        (SELECT COUNT(*) FROM online_exam_attempts t WHERE t.exam_id = e.id) AS attempts,
                        (SELECT AVG(t.score / t.max_score * 100) FROM online_exam_attempts t WHERE t.exam_id = e.id AND t.status = "graded" AND t.max_score > 0) AS avg_pct
                   FROM online_exams e WHERE e.offering_id = ? ORDER BY e.opens_at', [(int) $id]
            ),
            'assignments'=> Database::select(
                'SELECT a.*, (SELECT COUNT(*) FROM assignment_submissions sub WHERE sub.assignment_id = a.id) AS submitted
                   FROM assignments a WHERE a.offering_id = ? ORDER BY a.due_at', [(int) $id]
            ),
            'canTeach'   => Auth::can('teaching.access') && CourseAccess::mayTeach((int) $id),
        ]);
    }

    /* ---------------- Course materials (notes handed to the class) ---------------- */

    /**
     * Share a note, slide deck or reading with a class. The academic office does
     * this for classes whose lecturer is away, or for material issued centrally;
     * it goes to the students exactly as a lecturer's own upload does.
     */
    public function storeMaterial(Request $request, string $id): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $offering = CourseAccess::offering((int) $id);
        if ($offering === null) {
            $this->abort(404, 'Class not found.');
        }
        $back = '/academics/online-learning/' . (int) $id . '#materials';
        try {
            $materialId = ClassroomService::saveMaterial($offering, $request->all(), $request->file('file'), (int) Auth::id());
        } catch (RuntimeException $e) {
            Session::keepOldInput($request->all());
            $this->error($e->getMessage(), $back);
        }
        Session::clearOldInput();
        Audit::created('CourseMaterial', $materialId, ['offering_id' => (int) $id, 'title' => $request->input('title')], 'elearning');
        $this->success('Material shared with the class.', $back);
    }

    public function destroyMaterial(Request $request, string $materialId): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $material = Database::selectOne('SELECT * FROM course_materials WHERE id = ?', [(int) $materialId]);
        if ($material === null) {
            $this->abort(404, 'Material not found.');
        }
        ClassroomService::deleteMaterial($material);
        Audit::deleted('CourseMaterial', (int) $materialId, $material, 'elearning');
        $this->success('Material removed.', '/academics/online-learning/' . (int) $material['offering_id'] . '#materials');
    }

    /* ---------------- Scheduling & managing lessons ---------------- */

    public function create(Request $request): string
    {
        $this->authorize('elearning.manage');
        $offeringId = $request->int('offering') ?: null;
        return $this->view('academics.online-learning.lesson-form', [
            'pageTitle'          => 'Schedule Online Lesson',
            'offerings'          => Oversight::offerings(),
            'selectedOfferingId' => $offeringId,
            'session'            => null,
            'form'               => [
                'title'            => '',
                'description'      => '',
                'platform'         => 'jitsi',
                'meeting_url'      => '',
                'meeting_id'       => '',
                'passcode'         => '',
                'starts_at'        => date('Y-m-d H:00:00', strtotime('+1 day')),
                'duration_minutes' => 60,
                'recording_url'    => '',
            ],
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $offeringId = $request->int('offering_id');
        $back = '/academics/online-learning/create' . ($offeringId > 0 ? '?offering=' . $offeringId : '');
        $offering = $offeringId > 0 ? CourseAccess::offering($offeringId) : null;
        if ($offering === null || $offering['status'] === 'cancelled') {
            Session::keepOldInput($request->all());
            $this->error('Select an active class offering from the list.', $back);
        }
        $data = $this->sessionData($request, $offering, $back);
        ClassroomService::createSession($offering, $data, (int) Auth::id(), true);
        Session::clearOldInput();
        $this->success(
            'Online lesson scheduled for ' . $offering['code'] . '. Students and the lecturer have been notified.',
            '/academics/online-learning/' . $offeringId
        );
    }

    public function editSession(Request $request, string $id): string
    {
        $this->authorize('elearning.manage');
        $session  = $this->findSession((int) $id);
        $offering = CourseAccess::offering((int) $session['offering_id']);
        return $this->view('academics.online-learning.lesson-form', [
            'pageTitle'          => 'Edit Online Lesson - ' . $session['title'],
            'offerings'          => [],
            'selectedOfferingId' => (int) $offering['id'],
            'offering'           => $offering,
            'session'            => $session,
            'form'               => $session,
        ]);
    }

    public function updateSession(Request $request, string $id): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $session  = $this->findSession((int) $id);
        $offering = CourseAccess::offering((int) $session['offering_id']);
        $back     = '/academics/online-learning/sessions/' . $session['id'] . '/edit';
        $data     = $this->sessionData($request, $offering, $back, $session, true);
        ClassroomService::updateSession($session, $data);
        Session::clearOldInput();
        $this->success('Online lesson updated.', '/academics/online-learning/' . $offering['id']);
    }

    public function sessionStatus(Request $request, string $id): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $session  = $this->findSession((int) $id);
        $offering = CourseAccess::offering((int) $session['offering_id']);
        $action   = (string) $request->input('action');
        if (!in_array($action, ['cancel', 'complete'], true)) {
            $this->error('Unknown action.', '/academics/online-learning/' . $offering['id']);
        }
        ClassroomService::setSessionStatus($session, $offering, $action);
        $this->success(
            $action === 'cancel' ? 'Online lesson cancelled; students have been notified.' : 'Online lesson marked as held.',
            '/academics/online-learning/' . $offering['id']
        );
    }

    public function destroySession(Request $request, string $id): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $session = $this->findSession((int) $id);
        ClassroomService::deleteSession($session);
        $this->success('Online lesson deleted.', '/academics/online-learning/' . $session['offering_id']);
    }

    private function findSession(int $id): array
    {
        $session = Database::selectOne('SELECT * FROM class_sessions WHERE id = ?', [$id]);
        if ($session === null) {
            $this->abort(404, 'Online lesson not found.');
        }
        return $session;
    }

    private function sessionData(Request $request, array $offering, string $back, ?array $existing = null, bool $withRecording = false): array
    {
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

    /* ------------------------------------------------------------------ */

    private function kpis(array $scope, string $now): array
    {
        $base = implode(' AND ', $scope['where']);
        $from = ' FROM class_sessions s JOIN course_offerings o ON o.id = s.offering_id JOIN courses c ON c.id = o.course_id WHERE ' . $base;
        $endExpr = 'DATE_ADD(s.starts_at, INTERVAL s.duration_minutes MINUTE)';

        $sessions = Database::selectOne(
            "SELECT
                COALESCE(SUM(s.status = 'scheduled' AND s.starts_at <= ? AND {$endExpr} >= ?), 0) AS live,
                COALESCE(SUM(s.status = 'scheduled' AND s.starts_at > ? AND s.starts_at <= DATE_ADD(?, INTERVAL 7 DAY)), 0) AS next7,
                COALESCE(SUM(s.status <> 'cancelled' AND (s.status = 'completed' OR {$endExpr} < ?)), 0) AS held,
                COALESCE(SUM(s.status = 'cancelled'), 0) AS cancelled" . $from,
            array_merge([$now, $now, $now, $now, $now], $scope['params'])
        ) ?? [];

        $attendance = Database::selectOne(
            "SELECT COALESCE(SUM((SELECT COUNT(*) FROM class_session_attendance a WHERE a.session_id = s.id)), 0) AS joined,
                    COALESCE(SUM(" . Oversight::REGISTERED . "), 0) AS expected" . $from
            . " AND s.status <> 'cancelled' AND (s.status = 'completed' OR {$endExpr} < ?)",
            array_merge($scope['params'], [$now])
        ) ?? [];

        $coverage = Database::selectOne(
            'SELECT COUNT(*) AS classes,
                    COALESCE(SUM(EXISTS (SELECT 1 FROM class_sessions s WHERE s.offering_id = o.id AND s.status <> "cancelled")
                              OR EXISTS (SELECT 1 FROM course_materials m WHERE m.offering_id = o.id AND m.is_published = 1)
                              OR EXISTS (SELECT 1 FROM online_exams e WHERE e.offering_id = o.id AND e.status <> "draft")
                              OR EXISTS (SELECT 1 FROM assignments a WHERE a.offering_id = o.id AND a.status = "published")), 0) AS active,
                    (SELECT COUNT(*) FROM course_materials m
                      WHERE m.is_published = 1 AND m.offering_id IN (SELECT o.id FROM course_offerings o JOIN courses c ON c.id = o.course_id WHERE ' . $base . ')) AS materials
               FROM course_offerings o JOIN courses c ON c.id = o.course_id WHERE ' . $base,
            array_merge($scope['params'], $scope['params'])
        ) ?? [];

        return [
            'live'       => (int) ($sessions['live'] ?? 0),
            'next7'      => (int) ($sessions['next7'] ?? 0),
            'held'       => (int) ($sessions['held'] ?? 0),
            'cancelled'  => (int) ($sessions['cancelled'] ?? 0),
            'attendance' => Oversight::pct($attendance['joined'] ?? 0, $attendance['expected'] ?? 0),
            'classes'    => (int) ($coverage['classes'] ?? 0),
            'active'     => (int) ($coverage['active'] ?? 0),
            'materials'  => (int) ($coverage['materials'] ?? 0),
        ];
    }

    private function sessions(Request $request, string $base, array $params, string $now): array
    {
        $phase = (string) $request->query('phase', '');
        $endExpr = 'DATE_ADD(s.starts_at, INTERVAL s.duration_minutes MINUTE)';
        $phaseSql = match ($phase) {
            'live'      => [" AND s.status = 'scheduled' AND s.starts_at <= ? AND {$endExpr} >= ?", [$now, $now]],
            'upcoming'  => [" AND s.status = 'scheduled' AND s.starts_at > ?", [$now]],
            'ended'     => [" AND s.status <> 'cancelled' AND (s.status = 'completed' OR {$endExpr} < ?)", [$now]],
            'cancelled' => [" AND s.status = 'cancelled'", []],
            default     => ['', []],
        };
        if ($phaseSql[0] === '') {
            $phase = '';
        }

        $from = 'FROM class_sessions s
                 JOIN course_offerings o ON o.id = s.offering_id
                 JOIN courses c          ON c.id = o.course_id
            LEFT JOIN departments d      ON d.id = c.department_id
                 ' . Oversight::LECTURER_JOIN . '
                WHERE ' . $base . $phaseSql[0];
        // Live first, then what is coming up (soonest first), then the rest (most recent first).
        $rank = "CASE WHEN s.status = 'scheduled' AND s.starts_at <= ? AND {$endExpr} >= ? THEN 0
                      WHEN s.status = 'scheduled' AND s.starts_at > ? THEN 1 ELSE 2 END";
        $select = 'SELECT s.*, o.id AS offering_id, o.section, o.delivery_mode, c.code, c.title AS course_title, d.name AS department_name,
                          ' . Oversight::LECTURER_NAME . ' AS lecturer_name, ' . Oversight::REGISTERED . ' AS registered,
                          (SELECT COUNT(*) FROM class_session_attendance a WHERE a.session_id = s.id) AS attendees';
        $params = array_merge($params, $phaseSql[1]);

        if ($request->query('export') === 'csv') {
            $rows = Database::select($select . ' ' . $from . ' ORDER BY s.starts_at DESC', $params);
            Response::csv(array_map(static fn (array $r): array => [
                $r['starts_at'], $r['duration_minutes'], $r['code'], $r['section'], $r['course_title'], $r['title'],
                $r['lecturer_name'], $r['department_name'], ClassroomService::PLATFORMS[$r['platform']] ?? $r['platform'],
                self::PHASES[ClassroomService::phase($r)] ?? '', $r['attendees'], $r['registered'],
                Oversight::pct($r['attendees'], $r['registered']) ?? '',
            ], $rows), 'online-classes-' . date('Ymd') . '.csv', [
                'Starts', 'Minutes', 'Course', 'Section', 'Course title', 'Session', 'Lecturer', 'Department',
                'Platform', 'Status', 'Joined', 'Registered', 'Attendance %',
            ]);
        }

        $result = Oversight::paginate($select, $from, $params, $this->page($request), $this->perPage($request, 25),
            "{$rank}, CASE WHEN {$rank} < 2 THEN s.starts_at END ASC, s.starts_at DESC",
            [$now, $now, $now, $now, $now, $now]);
        return [$result, $phase];
    }

    private function coverage(Request $request, string $base, array $params, string $now): array
    {
        $activity = (string) $request->query('activity', '');
        $endExpr = 'DATE_ADD(s.starts_at, INTERVAL s.duration_minutes MINUTE)';
        $select = "SELECT o.id, o.section, o.delivery_mode, c.code, c.title AS course_title, d.name AS department_name,
                          " . Oversight::LECTURER_NAME . " AS lecturer_name, " . Oversight::REGISTERED . " AS registered,
                          (SELECT COUNT(*) FROM class_sessions s WHERE s.offering_id = o.id AND s.status <> 'cancelled'
                              AND (s.status = 'completed' OR {$endExpr} < ?)) AS held,
                          (SELECT COUNT(*) FROM class_sessions s WHERE s.offering_id = o.id AND s.status = 'scheduled' AND s.starts_at > ?) AS upcoming,
                          (SELECT COUNT(*) FROM course_materials m WHERE m.offering_id = o.id AND m.is_published = 1) AS materials,
                          (SELECT COUNT(*) FROM online_exams e WHERE e.offering_id = o.id AND e.status <> 'draft') AS exams,
                          (SELECT COUNT(*) FROM assignments a WHERE a.offering_id = o.id AND a.status = 'published') AS assignments,
                          NULLIF(GREATEST(
                              COALESCE((SELECT MAX(s.starts_at) FROM class_sessions s WHERE s.offering_id = o.id AND s.status <> 'cancelled' AND s.starts_at <= ?), '1000-01-01 00:00:00'),
                              COALESCE((SELECT MAX(m.created_at) FROM course_materials m WHERE m.offering_id = o.id), '1000-01-01 00:00:00'),
                              COALESCE((SELECT MAX(e.opens_at) FROM online_exams e WHERE e.offering_id = o.id AND e.status <> 'draft' AND e.opens_at <= ?), '1000-01-01 00:00:00')
                          ), '1000-01-01 00:00:00') AS last_activity";
        $selectParams = [$now, $now, $now, $now];

        $noActivity = "NOT EXISTS (SELECT 1 FROM class_sessions s WHERE s.offering_id = o.id AND s.status <> 'cancelled')
                   AND NOT EXISTS (SELECT 1 FROM course_materials m WHERE m.offering_id = o.id AND m.is_published = 1)
                   AND NOT EXISTS (SELECT 1 FROM online_exams e WHERE e.offering_id = o.id AND e.status <> 'draft')
                   AND NOT EXISTS (SELECT 1 FROM assignments a WHERE a.offering_id = o.id AND a.status = 'published')";
        $filter = match ($activity) {
            'none'   => ' AND ' . $noActivity,
            'active' => ' AND NOT (' . $noActivity . ')',
            default  => '',
        };
        if ($filter === '') {
            $activity = '';
        }
        $from = 'FROM course_offerings o
                 JOIN courses c     ON c.id = o.course_id
            LEFT JOIN departments d ON d.id = c.department_id
                 ' . Oversight::LECTURER_JOIN . '
                WHERE ' . $base . $filter;

        // The SELECT's placeholders come before the WHERE's, so they lead the parameter list.
        if ($request->query('export') === 'csv') {
            $rows = Database::select($select . ' ' . $from . ' ORDER BY c.code, o.section', array_merge($selectParams, $params));
            Response::csv(array_map(static fn (array $r): array => [
                $r['code'], $r['section'], $r['course_title'], $r['lecturer_name'], $r['department_name'], humanize($r['delivery_mode']),
                $r['registered'], $r['held'], $r['upcoming'], $r['materials'], $r['exams'], $r['assignments'], $r['last_activity'] ?? '',
            ], $rows), 'online-learning-coverage-' . date('Ymd') . '.csv', [
                'Course', 'Section', 'Course title', 'Lecturer', 'Department', 'Delivery', 'Registered',
                'Classes held', 'Classes scheduled', 'Materials', 'Online exams', 'Assignments', 'Last activity',
            ]);
        }

        $total = (int) Database::scalar('SELECT COUNT(*) ' . $from, $params);
        $perPage = $this->perPage($request, 25);
        $last = max(1, (int) ceil($total / $perPage));
        $page = min($this->page($request), $last);
        $rows = Database::select(
            $select . ' ' . $from . ' ORDER BY c.code, o.section LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            array_merge($selectParams, $params)
        );
        return [[
            'data' => $rows, 'total' => $total, 'page' => $page, 'last_page' => $last, 'per_page' => $perPage,
            'from' => $total === 0 ? 0 : ($page - 1) * $perPage + 1, 'to' => min($total, $page * $perPage),
        ], $activity];
    }
}
