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
use App\Services\Elearning\CourseAccess;
use App\Services\Elearning\ExamService;
use App\Services\Elearning\Oversight;
use RuntimeException;

/**
 * The online examinations register for the academic office: every quiz, test
 * and examination across all classes, with participation, marking backlog,
 * results and integrity flags. Read-only - lecturers set and mark their own
 * papers from Teaching.
 */
final class OnlineExamController extends Controller
{
    private const PHASES = ['open' => 'Open now', 'upcoming' => 'Upcoming', 'closed' => 'Closed', 'draft' => 'Draft'];
    private const PHASE_BADGE = ['draft' => 'draft', 'upcoming' => 'planned', 'open' => 'open', 'closed' => 'closed'];

    public function index(Request $request): string
    {
        $this->authorize('elearning.view');
        $now   = date('Y-m-d H:i:s');
        $scope = Oversight::scope($request, ['c.code', 'c.title', 'e.title']);
        $base  = implode(' AND ', $scope['where']);
        $params = $scope['params'];

        $phase = (string) $request->query('phase', '');
        $phaseSql = match ($phase) {
            'draft'    => [" AND e.status = 'draft'", []],
            'upcoming' => [" AND e.status = 'published' AND e.opens_at > ?", [$now]],
            'open'     => [" AND e.status = 'published' AND e.opens_at <= ? AND e.closes_at >= ?", [$now, $now]],
            'closed'   => [" AND e.status <> 'draft' AND (e.status = 'closed' OR e.closes_at < ?)", [$now]],
            default    => ['', []],
        };
        if ($phaseSql[0] === '') {
            $phase = '';
        }
        $type = (string) $request->query('type', '');
        $typeSql = isset(ExamService::TYPES[$type]) ? ' AND e.exam_type = ?' : '';
        $flagged = $request->query('flag') === 'marking' ? ' AND COALESCE(t.to_mark, 0) > 0'
                 : ($request->query('flag') === 'integrity' ? ' AND COALESCE(t.flagged, 0) > 0' : '');

        $attempts = "LEFT JOIN (SELECT exam_id, COUNT(*) AS attempts,
                                       SUM(status = 'in_progress' AND deadline_at >= ?) AS writing,
                                       SUM(status = 'submitted') AS to_mark,
                                       SUM(status = 'graded') AS graded,
                                       AVG(CASE WHEN status = 'graded' AND max_score > 0 THEN score / max_score * 100 END) AS avg_pct,
                                       SUM(focus_lost > 0) AS flagged
                                  FROM online_exam_attempts GROUP BY exam_id) t ON t.exam_id = e.id";
        $from = 'FROM online_exams e
                 JOIN course_offerings o ON o.id = e.offering_id
                 JOIN courses c          ON c.id = o.course_id
                 JOIN semesters sem      ON sem.id = o.semester_id
            LEFT JOIN departments d      ON d.id = c.department_id
                 ' . Oversight::LECTURER_JOIN . '
                 ' . $attempts . '
                WHERE ' . $base . $phaseSql[0] . $typeSql . $flagged;
        $fromParams = array_merge([$now], $params, $phaseSql[1], $typeSql !== '' ? [$type] : []);
        $select = 'SELECT e.*, o.id AS offering_id, o.section, c.code, c.title AS course_title, sem.name AS semester_name,
                          d.name AS department_name, ' . Oversight::LECTURER_NAME . ' AS lecturer_name, ' . Oversight::REGISTERED . ' AS registered,
                          (SELECT COUNT(*) FROM online_exam_questions q WHERE q.exam_id = e.id) AS question_count,
                          COALESCE(t.attempts, 0) AS attempts, COALESCE(t.writing, 0) AS writing, COALESCE(t.to_mark, 0) AS to_mark,
                          COALESCE(t.graded, 0) AS graded, t.avg_pct, COALESCE(t.flagged, 0) AS flagged';

        if ($request->query('export') === 'csv') {
            $rows = Database::select($select . ' ' . $from . ' ORDER BY e.opens_at DESC', $fromParams);
            Response::csv(array_map(static fn (array $r): array => [
                $r['title'], ExamService::TYPES[$r['exam_type']] ?? $r['exam_type'], $r['code'], $r['section'], $r['course_title'],
                $r['semester_name'], $r['lecturer_name'], $r['department_name'], $r['opens_at'], $r['closes_at'], $r['duration_minutes'],
                self::PHASES[ExamService::phase($r)] ?? '', $r['question_count'], $r['attempts'], $r['registered'],
                Oversight::pct($r['attempts'], $r['registered']) ?? '', $r['to_mark'],
                $r['avg_pct'] !== null ? round((float) $r['avg_pct'], 1) : '', $r['flagged'],
            ], $rows), 'online-exams-' . date('Ymd') . '.csv', [
                'Exam', 'Type', 'Course', 'Section', 'Course title', 'Semester', 'Lecturer', 'Department', 'Opens', 'Closes',
                'Minutes', 'Status', 'Questions', 'Sat', 'Registered', 'Participation %', 'To mark', 'Average %', 'Integrity flags',
            ]);
        }

        // Open first, then upcoming (soonest first), then closed (most recent first), drafts last.
        $rank = "CASE WHEN e.status = 'published' AND e.opens_at <= ? AND e.closes_at >= ? THEN 0
                      WHEN e.status = 'published' AND e.opens_at > ? THEN 1
                      WHEN e.status = 'draft' THEN 3 ELSE 2 END";
        $result = Oversight::paginate($select, $from, $fromParams, $this->page($request), $this->perPage($request, 25),
            "{$rank}, CASE WHEN {$rank} = 1 THEN e.opens_at END ASC, e.closes_at DESC",
            [$now, $now, $now, $now, $now, $now]);

        return $this->view('academics.online-exams.index', Oversight::lookups() + [
            'pageTitle' => 'Online Examinations',
            'kpis'      => $this->kpis(Oversight::scope($request, ['c.code', 'c.title', 'e.title']), $now),
            'result'    => $result,
            'filters'   => $scope['filters'] + ['phase' => $phase, 'type' => $typeSql !== '' ? $type : '', 'flag' => (string) $request->query('flag', '')],
            'phases'    => self::PHASES,
            'badges'    => self::PHASE_BADGE,
        ]);
    }

    /** One exam: its settings, paper composition, results, and every registered student's attempt. */
    public function show(Request $request, string $id): string
    {
        $this->authorize('elearning.view');
        $exam = ExamService::find((int) $id);
        if ($exam === null) {
            $this->abort(404, 'Exam not found.');
        }
        $offering = CourseAccess::offering((int) $exam['offering_id']);
        // Papers whose time has run out are submitted, as on the lecturer's results page.
        ExamService::expireDueAttempts((int) $id);

        $rows = Database::select(
            'SELECT cr.student_id, s.admission_number, u.first_name, u.last_name,
                    t.id AS attempt_id, t.status, t.started_at, t.submitted_at, t.score, t.max_score, t.auto_submitted, t.focus_lost
               FROM course_registrations cr
               JOIN students s ON s.id = cr.student_id
               JOIN users u    ON u.id = s.user_id
          LEFT JOIN online_exam_attempts t ON t.exam_id = ? AND t.student_id = cr.student_id
              WHERE cr.offering_id = ? AND cr.status <> "dropped"
              ORDER BY t.id IS NULL, u.last_name, u.first_name',
            [(int) $id, (int) $exam['offering_id']]
        );

        if ($request->query('export') === 'csv') {
            Response::csv(array_map(static fn (array $r): array => [
                $r['admission_number'], $r['last_name'] . ', ' . $r['first_name'],
                $r['attempt_id'] === null ? 'not attempted' : $r['status'], $r['started_at'] ?? '', $r['submitted_at'] ?? '',
                $r['score'] ?? '', $r['max_score'] ?? $exam['total_marks'],
                $r['status'] === 'graded' ? (Oversight::pct($r['score'], $r['max_score']) ?? '') : '',
                (int) ($r['auto_submitted'] ?? 0) === 1 ? 'yes' : '', (int) ($r['focus_lost'] ?? 0),
            ], $rows), 'online-exam-' . $exam['id'] . '-register.csv',
                ['Student ID', 'Name', 'Status', 'Started', 'Submitted', 'Score', 'Out of', 'Percent', 'Timed out', 'Left window']);
        }

        $graded = array_values(array_filter($rows, static fn (array $r): bool => $r['status'] === 'graded'));
        $pcts   = array_map(static fn (array $r): float => (float) $r['max_score'] > 0 ? (float) $r['score'] / (float) $r['max_score'] * 100 : 0.0, $graded);
        $bands  = ['70% and above' => 0, '60 - 69%' => 0, '50 - 59%' => 0, '40 - 49%' => 0, 'Below 40%' => 0];
        foreach ($pcts as $p) {
            $bands[match (true) { $p >= 70 => '70% and above', $p >= 60 => '60 - 69%', $p >= 50 => '50 - 59%', $p >= 40 => '40 - 49%', default => 'Below 40%' }]++;
        }

        return $this->view('academics.online-exams.show', [
            'pageTitle'   => $exam['title'],
            'exam'        => $exam,
            'phase'       => ExamService::phase($exam),
            'badges'      => self::PHASE_BADGE,
            'phases'      => self::PHASES,
            'offering'    => $offering,
            'rows'        => $rows,
            'bands'       => $bands,
            'stats'       => [
                'registered'  => count($rows),
                'sat'         => count(array_filter($rows, static fn (array $r): bool => $r['attempt_id'] !== null)),
                'in_progress' => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'in_progress')),
                'to_mark'     => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'submitted')),
                'graded'      => count($graded),
                'average'     => $pcts === [] ? null : round(array_sum($pcts) / count($pcts), 1),
                'median'      => $pcts === [] ? null : round($this->median($pcts), 1),
                'highest'     => $pcts === [] ? null : round(max($pcts), 1),
                'lowest'      => $pcts === [] ? null : round(min($pcts), 1),
                'passed'      => count(array_filter($pcts, static fn (float $p): bool => $p >= (float) $exam['pass_percent'])),
                'flagged'     => count(array_filter($rows, static fn (array $r): bool => (int) $r['focus_lost'] > 0)),
                'timed_out'   => count(array_filter($rows, static fn (array $r): bool => (int) $r['auto_submitted'] === 1)),
            ],
            // Composition only: question wording stays with the lecturer, so a paper cannot leak before it opens.
            'composition' => Database::select(
                'SELECT question_type, COUNT(*) AS questions, SUM(marks) AS marks FROM online_exam_questions
                  WHERE exam_id = ? GROUP BY question_type ORDER BY FIELD(question_type, "single", "multiple", "true_false", "short_answer", "essay")',
                [(int) $id]
            ),
            'assessment'  => $exam['assessment_id'] ? Database::selectOne('SELECT * FROM assessments WHERE id = ?', [$exam['assessment_id']]) : null,
            'problems'    => ExamService::publishProblems($exam),
            'hasAttempts' => ExamService::hasAttempts((int) $id),
            'canTeach'    => Auth::can('teaching.access') && CourseAccess::mayTeach((int) $exam['offering_id']),
        ]);
    }

    /* ---------------- The question paper ---------------- */

    /**
     * Set the paper. The oversight page shows composition only, so a paper cannot
     * leak to everyone who may merely view online learning; the wording lives
     * here, behind elearning.manage, where the academic office sets questions for
     * a class whose lecturer is away, or for a paper issued centrally.
     */
    public function questions(Request $request, string $id): string
    {
        $this->authorize('elearning.manage');
        $exam      = $this->examOrFail((int) $id);
        $questions = ExamService::questions((int) $id);
        $editing   = null;
        foreach ($questions as $question) {
            if ($request->filled('question') && (int) $question['id'] === $request->int('question')) {
                $editing = $question;
            }
        }

        return $this->view('academics.online-exams.questions', [
            'pageTitle' => 'Question paper - ' . $exam['title'],
            'exam'      => $exam,
            'offering'  => CourseAccess::offering((int) $exam['offering_id']),
            'phase'     => ExamService::phase($exam),
            'questions' => $questions,
            'editing'   => $editing,
            'locked'    => ExamService::hasAttempts((int) $id),
            'problems'  => ExamService::publishProblems($exam),
        ]);
    }

    public function saveQuestion(Request $request, string $id): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $exam = $this->examOrFail((int) $id);
        $back = '/academics/online-exams/' . (int) $id . '/questions';
        if (ExamService::hasAttempts((int) $id)) {
            $this->error('Students have started this exam, so its questions are locked.', $back);
        }

        $type    = (string) $request->input('question_type', 'single');
        $correct = array_map('strval', (array) $request->input('option_correct', []));
        $options = [];
        foreach ((array) $request->input('option_body', []) as $i => $body) {
            $options[] = ['body' => (string) $body, 'correct' => in_array((string) $i, $correct, true)];
        }
        if ($type === 'true_false') {
            $options = [['body' => $request->input('true_false_answer') === 'false' ? 'false' : 'true', 'correct' => true]];
        }
        $questionId = $request->filled('question_id') ? (int) $request->input('question_id') : null;
        try {
            ExamService::saveQuestion((int) $exam['id'], $questionId, $type, (string) $request->input('body', ''),
                (float) $request->input('marks', 1), trim((string) $request->input('explanation', '')) ?: null, $options);
        } catch (RuntimeException $e) {
            Session::keepOldInput($request->all());
            $this->error($e->getMessage(), $back . ($questionId ? '?question=' . $questionId : '') . '#question-form');
        }
        Session::clearOldInput();
        Audit::log($questionId ? 'update' : 'create', 'elearning', 'ExamQuestion', (string) $questionId,
            'Question set on online exam #' . $exam['id']);
        $this->success($questionId ? 'Question updated.' : 'Question added.', $back . '#question-form');
    }

    public function destroyQuestion(Request $request, string $questionId): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $question = Database::selectOne('SELECT * FROM online_exam_questions WHERE id = ?', [(int) $questionId]);
        if ($question === null) {
            $this->abort(404, 'Question not found.');
        }
        $back = '/academics/online-exams/' . (int) $question['exam_id'] . '/questions';
        if (ExamService::hasAttempts((int) $question['exam_id'])) {
            $this->error('Students have started this exam, so its questions are locked.', $back);
        }
        Database::statement('DELETE FROM online_exam_questions WHERE id = ?', [$question['id']]);
        Audit::deleted('ExamQuestion', (int) $questionId, $question, 'elearning');
        $this->success('Question deleted.', $back);
    }

    /** Move a question up or down the paper. */
    public function moveQuestion(Request $request, string $questionId): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $question = Database::selectOne('SELECT * FROM online_exam_questions WHERE id = ?', [(int) $questionId]);
        if ($question === null) {
            $this->abort(404, 'Question not found.');
        }
        $ids = array_map('intval', array_column(Database::select(
            'SELECT id FROM online_exam_questions WHERE exam_id = ? ORDER BY sort_order, id', [$question['exam_id']]
        ), 'id'));
        $at = array_search((int) $question['id'], $ids, true);
        $to = $request->input('direction') === 'up' ? $at - 1 : $at + 1;
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            foreach ($ids as $i => $questionIdInOrder) {
                Database::statement('UPDATE online_exam_questions SET sort_order = ? WHERE id = ?', [$i + 1, $questionIdInOrder]);
            }
        }
        $this->redirect('/academics/online-exams/' . (int) $question['exam_id'] . '/questions#q' . (int) $question['id']);
    }

    /* ---------------- Marking written answers ---------------- */

    /** One student's paper, for marking the answers a computer cannot mark. */
    public function attempt(Request $request, string $attemptId): string
    {
        $this->authorize('elearning.manage');
        $attempt = Database::selectOne(
            'SELECT t.*, s.admission_number, u.first_name, u.last_name
               FROM online_exam_attempts t JOIN students s ON s.id = t.student_id JOIN users u ON u.id = s.user_id
              WHERE t.id = ?',
            [(int) $attemptId]
        );
        if ($attempt === null) {
            $this->abort(404, 'Attempt not found.');
        }
        $exam    = $this->examOrFail((int) $attempt['exam_id']);
        $attempt = ExamService::expireIfDue($attempt) + $attempt;

        return $this->view('academics.online-exams.attempt', [
            'pageTitle' => 'Marking - ' . $attempt['first_name'] . ' ' . $attempt['last_name'],
            'exam'      => $exam,
            'offering'  => CourseAccess::offering((int) $exam['offering_id']),
            'attempt'   => $attempt,
            'questions' => ExamService::questions((int) $exam['id']),
            'answers'   => array_column(Database::select('SELECT * FROM online_exam_answers WHERE attempt_id = ?', [$attempt['id']]), null, 'question_id'),
        ]);
    }

    public function mark(Request $request, string $attemptId): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $attempt = Database::selectOne('SELECT * FROM online_exam_attempts WHERE id = ?', [(int) $attemptId]);
        if ($attempt === null) {
            $this->abort(404, 'Attempt not found.');
        }
        try {
            ExamService::mark((int) $attemptId, (array) $request->input('marks', []), (array) $request->input('feedback', []), (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/academics/online-exams/attempts/' . (int) $attemptId);
        }
        Audit::log('mark', 'elearning', 'ExamAttempt', (string) (int) $attemptId, 'Marked an online exam attempt');
        $this->success('Marks saved.', '/academics/online-exams/' . (int) $attempt['exam_id']);
    }

    private function examOrFail(int $id): array
    {
        $exam = ExamService::find($id);
        if ($exam === null) {
            $this->abort(404, 'Exam not found.');
        }
        return $exam;
    }

    /* ---------------- Scheduling & managing exams ---------------- */

    public function create(Request $request): string
    {
        $this->authorize('elearning.manage');
        $offeringId = $request->int('offering') ?: null;
        return $this->view('academics.online-exams.form', [
            'pageTitle'          => 'Schedule Online Exam',
            'offerings'          => Oversight::offerings(),
            'selectedOfferingId' => $offeringId,
            'offering'           => null,
            'lockedDuration'     => false,
            'exam'               => [
                'title'               => '',
                'exam_type'           => 'quiz',
                'instructions'        => '',
                'duration_minutes'    => 60,
                'pass_percent'        => 50,
                'opens_at'            => date('Y-m-d H:00:00', strtotime('+1 day')),
                'closes_at'           => date('Y-m-d H:00:00', strtotime('+2 days')),
                'shuffle_questions'   => 1,
                'shuffle_options'     => 1,
                'requires_enrollment' => 1,
                'show_results'        => 'after_close',
            ],
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $offeringId = $request->int('offering_id');
        $back = '/academics/online-exams/create' . ($offeringId > 0 ? '?offering=' . $offeringId : '');
        $offering = $offeringId > 0 ? CourseAccess::offering($offeringId) : null;
        if ($offering === null || $offering['status'] === 'cancelled') {
            Session::keepOldInput($request->all());
            $this->error('Select an active class offering from the list.', $back);
        }
        $data   = $this->examData($request, $back);
        $examId = ExamService::createExam($offering, $data, (int) Auth::id(), true);
        Session::clearOldInput();
        $this->success(
            'Online exam scheduled as a draft for ' . $offering['code'] . '. The lecturer has been notified to add questions before publishing.',
            '/academics/online-exams/' . $examId
        );
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('elearning.manage');
        $exam = ExamService::find((int) $id);
        if ($exam === null) {
            $this->abort(404, 'Exam not found.');
        }
        return $this->view('academics.online-exams.form', [
            'pageTitle'          => 'Edit Exam Schedule - ' . $exam['title'],
            'offerings'          => [],
            'selectedOfferingId' => (int) $exam['offering_id'],
            'offering'           => CourseAccess::offering((int) $exam['offering_id']),
            'lockedDuration'     => ExamService::hasAttempts((int) $id),
            'exam'               => $exam,
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $exam = ExamService::find((int) $id);
        if ($exam === null) {
            $this->abort(404, 'Exam not found.');
        }
        $back = '/academics/online-exams/' . $id . '/edit';
        $data = $this->examData($request, $back);
        try {
            ExamService::updateExam($exam, $data);
        } catch (RuntimeException $e) {
            Session::keepOldInput($request->all());
            $this->error($e->getMessage(), $back);
        }
        Session::clearOldInput();
        $this->success('Online exam schedule saved.', '/academics/online-exams/' . $id);
    }

    public function status(Request $request, string $id): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $exam = ExamService::find((int) $id);
        if ($exam === null) {
            $this->abort(404, 'Exam not found.');
        }
        $offering = CourseAccess::offering((int) $exam['offering_id']);
        $back     = '/academics/online-exams/' . $id;

        try {
            switch ($request->input('action')) {
                case 'publish':
                    ExamService::publish($exam, $offering);
                    $this->success('Exam published; students on the class list have been notified.', $back);
                case 'unpublish':
                    ExamService::unpublish((int) $exam['id']);
                    $this->success('Exam moved back to draft.', $back);
                case 'close':
                    ExamService::close((int) $exam['id']);
                    $this->success('Exam closed. Any open attempts were submitted.', $back);
                case 'release':
                    ExamService::setResultsReleased($exam, $offering, true);
                    $this->success('Results released to students.', $back);
                case 'withhold':
                    ExamService::setResultsReleased($exam, $offering, false);
                    $this->success('Results hidden from students.', $back);
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), $back);
        }
        $this->error('Unknown action.', $back);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('elearning.manage');
        $this->verifyCsrf($request);
        $exam = ExamService::find((int) $id);
        if ($exam === null) {
            $this->abort(404, 'Exam not found.');
        }
        try {
            ExamService::deleteExam($exam);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/academics/online-exams/' . $id);
        }
        $this->success('Online exam deleted.', '/academics/online-exams');
    }

    private function examData(Request $request, string $back): array
    {
        $data = $this->validate($request, ExamService::EXAM_RULES, ExamService::EXAM_LABELS);
        try {
            return ExamService::normalizeExamInput($data, [
                'shuffle_questions'   => $request->input('shuffle_questions'),
                'shuffle_options'     => $request->input('shuffle_options'),
                'requires_enrollment' => $request->input('requires_enrollment'),
            ]);
        } catch (RuntimeException $e) {
            Session::keepOldInput($request->all());
            $this->error($e->getMessage(), $back);
        }
    }

    /* ------------------------------------------------------------------ */

    private function kpis(array $scope, string $now): array
    {
        $base = implode(' AND ', $scope['where']);
        $from = ' FROM online_exams e JOIN course_offerings o ON o.id = e.offering_id JOIN courses c ON c.id = o.course_id WHERE ' . $base;

        $exams = Database::selectOne(
            "SELECT COALESCE(SUM(e.status = 'published' AND e.opens_at <= ? AND e.closes_at >= ?), 0) AS open_now,
                    COALESCE(SUM(e.status = 'published' AND e.opens_at > ? AND e.opens_at <= DATE_ADD(?, INTERVAL 7 DAY)), 0) AS next7,
                    COALESCE(SUM(e.status <> 'draft' AND (e.status = 'closed' OR e.closes_at < ?)), 0) AS closed,
                    COALESCE(SUM(e.status = 'draft'), 0) AS drafts,
                    COALESCE(SUM(CASE WHEN e.status <> 'draft' AND e.opens_at <= ? THEN " . Oversight::REGISTERED . " END), 0) AS expected" . $from,
            array_merge([$now, $now, $now, $now, $now, $now], $scope['params'])
        ) ?? [];

        $attempts = Database::selectOne(
            "SELECT COUNT(*) AS attempts,
                    COALESCE(SUM(t.status = 'in_progress' AND t.deadline_at >= ?), 0) AS writing,
                    COALESCE(SUM(t.status = 'submitted'), 0) AS to_mark,
                    AVG(CASE WHEN t.status = 'graded' AND t.max_score > 0 THEN t.score / t.max_score * 100 END) AS avg_pct,
                    COALESCE(SUM(t.focus_lost > 0), 0) AS flagged
               FROM online_exam_attempts t JOIN online_exams e ON e.id = t.exam_id
               JOIN course_offerings o ON o.id = e.offering_id JOIN courses c ON c.id = o.course_id
              WHERE " . $base,
            array_merge([$now], $scope['params'])
        ) ?? [];

        return [
            'open_now'      => (int) ($exams['open_now'] ?? 0),
            'next7'         => (int) ($exams['next7'] ?? 0),
            'closed'        => (int) ($exams['closed'] ?? 0),
            'drafts'        => (int) ($exams['drafts'] ?? 0),
            'writing'       => (int) ($attempts['writing'] ?? 0),
            'to_mark'       => (int) ($attempts['to_mark'] ?? 0),
            'average'       => isset($attempts['avg_pct']) ? round((float) $attempts['avg_pct'], 1) : null,
            'participation' => Oversight::pct($attempts['attempts'] ?? 0, $exams['expected'] ?? 0),
            'flagged'       => (int) ($attempts['flagged'] ?? 0),
        ];
    }

    private function median(array $values): float
    {
        sort($values);
        $n = count($values);
        return $n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }
}

