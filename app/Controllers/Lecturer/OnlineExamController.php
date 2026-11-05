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
use App\Core\Response;
use App\Core\Session;
use App\Services\Elearning\CourseAccess;
use App\Services\Elearning\ExamService;
use RuntimeException;

/** Lecturers set, run and mark online examinations for their classes. */
final class OnlineExamController extends Controller
{
    /** Every online exam in the classes this lecturer teaches (all classes for exams.edit holders). */
    public function mine(Request $request): string
    {
        $this->authorize('teaching.access');
        $where = '1 = 1';
        if (!Auth::can('exams.edit')) {
            $staffId = CourseAccess::staffId();
            $where   = 'e.offering_id IN (' . CourseAccess::inList($staffId === null ? [] : CourseAccess::taughtOfferingIds($staffId)) . ')';
        }
        $exams = Database::select(
            "SELECT e.*, c.code, c.title AS course_title, o.section, sem.name AS semester_name,
                    (SELECT COUNT(*) FROM online_exam_questions q WHERE q.exam_id = e.id) AS question_count,
                    (SELECT COUNT(*) FROM online_exam_attempts t WHERE t.exam_id = e.id) AS attempts,
                    (SELECT COUNT(*) FROM online_exam_attempts t WHERE t.exam_id = e.id AND t.status = 'submitted') AS to_mark,
                    o.enrolled_count
               FROM online_exams e
               JOIN course_offerings o ON o.id = e.offering_id
               JOIN courses c          ON c.id = o.course_id
               JOIN semesters sem      ON sem.id = o.semester_id
              WHERE {$where}
              ORDER BY e.closes_at DESC"
        );
        return $this->view('lecturer.online-exams.mine', ['pageTitle' => 'Online Examinations', 'exams' => $exams]);
    }

    public function create(Request $request, string $id): string
    {
        $this->authorize('teaching.access');
        $offering = CourseAccess::teaching((int) $id);
        return $this->view('lecturer.online-exams.form', [
            'pageTitle' => 'New online exam - ' . $offering['code'],
            'offering'  => $offering,
            'exam'      => [
                'title' => '', 'exam_type' => 'quiz', 'instructions' => '', 'duration_minutes' => 30, 'pass_percent' => 50,
                'opens_at' => date('Y-m-d H:00:00', strtotime('+1 day')), 'closes_at' => date('Y-m-d H:00:00', strtotime('+2 days')),
                'shuffle_questions' => 1, 'shuffle_options' => 1, 'requires_enrollment' => 1, 'show_results' => 'after_close',
            ],
        ]);
    }

    public function store(Request $request, string $id): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $offering = CourseAccess::teaching((int) $id);
        $data     = $this->examData($request, '/teaching/' . $id . '/online-exams/create');

        Database::statement(
            'INSERT INTO online_exams (offering_id, title, exam_type, instructions, opens_at, closes_at, duration_minutes, pass_percent,
                                       shuffle_questions, shuffle_options, requires_enrollment, show_results, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "draft", ?)',
            [(int) $id, $data['title'], $data['exam_type'], $data['instructions'], $data['opens_at'], $data['closes_at'],
             $data['duration_minutes'], $data['pass_percent'], $data['shuffle_questions'], $data['shuffle_options'],
             $data['requires_enrollment'], $data['show_results'], Auth::id()]
        );
        $examId = Database::lastInsertId();
        Audit::log('create', 'elearning', 'online_exams', (string) $examId, "Created online exam {$data['title']} for {$offering['code']}");
        $this->success('Exam created as a draft. Now add the questions, then publish it.', '/teaching/online-exams/' . $examId);
    }

    public function edit(Request $request, string $examId): string
    {
        $this->authorize('teaching.access');
        $exam = $this->exam((int) $examId);
        return $this->view('lecturer.online-exams.form', [
            'pageTitle' => 'Exam settings - ' . $exam['title'],
            'offering'  => CourseAccess::teaching((int) $exam['offering_id']),
            'exam'      => $exam,
        ]);
    }

    public function update(Request $request, string $examId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $exam = $this->exam((int) $examId);
        $data = $this->examData($request, '/teaching/online-exams/' . $examId . '/edit');
        if ($this->hasAttempts((int) $examId) && (int) $data['duration_minutes'] !== (int) $exam['duration_minutes']) {
            $this->error('Students have already started this exam, so its duration can no longer change.', '/teaching/online-exams/' . $examId . '/edit');
        }
        Database::statement(
            'UPDATE online_exams SET title = ?, exam_type = ?, instructions = ?, opens_at = ?, closes_at = ?, duration_minutes = ?,
                    pass_percent = ?, shuffle_questions = ?, shuffle_options = ?, requires_enrollment = ?, show_results = ?,
                    status = IF(status = "closed" AND ? > ?, "published", status)
              WHERE id = ?',
            [$data['title'], $data['exam_type'], $data['instructions'], $data['opens_at'], $data['closes_at'], $data['duration_minutes'],
             $data['pass_percent'], $data['shuffle_questions'], $data['shuffle_options'], $data['requires_enrollment'],
             $data['show_results'], $data['closes_at'], ExamService::now(), $examId]
        );
        $this->success('Exam settings saved.', '/teaching/online-exams/' . $examId);
    }

    /** The exam paper: its questions, and the authoring form. */
    public function show(Request $request, string $examId): string
    {
        $this->authorize('teaching.access');
        $exam     = $this->exam((int) $examId);
        $offering = CourseAccess::teaching((int) $exam['offering_id']);
        $editing  = null;
        if ($request->filled('question')) {
            $editing = Database::selectOne('SELECT * FROM online_exam_questions WHERE id = ? AND exam_id = ?', [(int) $request->query('question'), $exam['id']]);
            if ($editing !== null) {
                $editing['options'] = Database::select('SELECT * FROM online_exam_options WHERE question_id = ? ORDER BY sort_order', [$editing['id']]);
            }
        }
        return $this->view('lecturer.online-exams.show', [
            'pageTitle' => $exam['title'],
            'exam'      => $exam,
            'offering'  => $offering,
            'questions' => ExamService::questions((int) $examId),
            'locked'    => $this->hasAttempts((int) $examId),
            'editing'   => $editing,
            'problems'  => ExamService::publishProblems($exam),
        ]);
    }

    public function saveQuestion(Request $request, string $examId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $exam = $this->exam((int) $examId);
        $back = '/teaching/online-exams/' . $examId;
        if ($this->hasAttempts((int) $examId)) {
            $this->error('Students have started this exam, so its questions are locked.', $back);
        }
        $type    = (string) $request->input('question_type', 'single');
        $bodies  = (array) $request->input('option_body', []);
        $correct = array_map('strval', (array) $request->input('option_correct', []));
        $options = [];
        foreach ($bodies as $i => $body) {
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
        $this->success($questionId ? 'Question updated.' : 'Question added.', $back . '#question-form');
    }

    public function destroyQuestion(Request $request, string $questionId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $question = Database::selectOne('SELECT * FROM online_exam_questions WHERE id = ?', [(int) $questionId]);
        if ($question === null) {
            throw new HttpException(404);
        }
        $this->exam((int) $question['exam_id']);
        if ($this->hasAttempts((int) $question['exam_id'])) {
            $this->error('Students have started this exam, so its questions are locked.', '/teaching/online-exams/' . $question['exam_id']);
        }
        Database::statement('DELETE FROM online_exam_questions WHERE id = ?', [$question['id']]);
        $this->success('Question deleted.', '/teaching/online-exams/' . $question['exam_id']);
    }

    /** Move a question up or down the paper. */
    public function moveQuestion(Request $request, string $questionId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $question = Database::selectOne('SELECT * FROM online_exam_questions WHERE id = ?', [(int) $questionId]);
        if ($question === null) {
            throw new HttpException(404);
        }
        $this->exam((int) $question['exam_id']);
        $ids = array_map('intval', array_column(Database::select(
            'SELECT id FROM online_exam_questions WHERE exam_id = ? ORDER BY sort_order, id', [$question['exam_id']]
        ), 'id'));
        $at = array_search((int) $question['id'], $ids, true);
        $to = $request->input('direction') === 'up' ? $at - 1 : $at + 1;
        if ($at !== false && isset($ids[$to])) {
            [$ids[$at], $ids[$to]] = [$ids[$to], $ids[$at]];
            foreach ($ids as $i => $id) {
                Database::statement('UPDATE online_exam_questions SET sort_order = ? WHERE id = ?', [$i + 1, $id]);
            }
        }
        $this->redirect('/teaching/online-exams/' . $question['exam_id'] . '#q' . $question['id']);
    }

    /** publish | unpublish | close | release */
    public function status(Request $request, string $examId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $exam     = $this->exam((int) $examId);
        $offering = CourseAccess::teaching((int) $exam['offering_id']);
        $back     = '/teaching/online-exams/' . $examId;

        switch ($request->input('action')) {
            case 'publish':
                $problems = ExamService::publishProblems($exam);
                if ($problems !== []) {
                    $this->error(implode(' ', $problems), $back);
                }
                Database::statement('UPDATE online_exams SET status = "published" WHERE id = ?', [$exam['id']]);
                Notify::sendMany(
                    array_column(Database::select(
                        'SELECT s.user_id FROM course_registrations cr JOIN students s ON s.id = cr.student_id
                          WHERE cr.offering_id = ? AND cr.status <> "dropped"', [$exam['offering_id']]
                    ), 'user_id'),
                    'Online exam: ' . $offering['code'] . ' - ' . $exam['title'],
                    'Opens ' . fdatetime($exam['opens_at']) . ', closes ' . fdatetime($exam['closes_at']) . '. Time allowed: '
                        . $exam['duration_minutes'] . ' minutes.',
                    '/portal/exams/' . $exam['id'], 'info', 'clipboard'
                );
                Audit::log('publish', 'elearning', 'online_exams', (string) $exam['id'], 'Published online exam ' . $exam['title']);
                $this->success('Exam published; the class has been notified.', $back);
            case 'unpublish':
                if ($this->hasAttempts((int) $exam['id'])) {
                    $this->error('Students have already started this exam; close it instead.', $back);
                }
                Database::statement('UPDATE online_exams SET status = "draft" WHERE id = ?', [$exam['id']]);
                $this->success('Exam moved back to draft; students can no longer see it.', $back);
            case 'close':
                ExamService::close((int) $exam['id']);
                Audit::log('close', 'elearning', 'online_exams', (string) $exam['id'], 'Closed online exam ' . $exam['title']);
                $this->success('Exam closed. Attempts still open were submitted.', '/teaching/online-exams/' . $examId . '/results');
            case 'release':
                Database::statement('UPDATE online_exams SET results_released = 1 WHERE id = ?', [$exam['id']]);
                $this->success('Results released to students.', '/teaching/online-exams/' . $examId . '/results');
            case 'withhold':
                Database::statement('UPDATE online_exams SET results_released = 0 WHERE id = ?', [$exam['id']]);
                $this->success('Results hidden from students.', '/teaching/online-exams/' . $examId . '/results');
        }
        $this->error('Unknown action.', $back);
    }

    public function destroy(Request $request, string $examId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $exam = $this->exam((int) $examId);
        if ($this->hasAttempts((int) $examId)) {
            $this->error('Students have taken this exam, so it cannot be deleted. Close it instead.', '/teaching/online-exams/' . $examId);
        }
        Database::statement('DELETE FROM online_exams WHERE id = ?', [$exam['id']]);
        Audit::log('delete', 'elearning', 'online_exams', (string) $exam['id'], 'Deleted online exam ' . $exam['title']);
        $this->success('Exam deleted.', '/teaching/' . $exam['offering_id'] . '/classroom#exams');
    }

    /** Attempts, statistics and the students who have not sat the exam. */
    public function results(Request $request, string $examId): string
    {
        $this->authorize('teaching.access');
        $exam     = $this->exam((int) $examId);
        $offering = CourseAccess::teaching((int) $exam['offering_id']);
        ExamService::expireDueAttempts((int) $examId);

        $rows = Database::select(
            'SELECT cr.student_id, s.admission_number, u.first_name, u.last_name,
                    t.id AS attempt_id, t.status, t.started_at, t.submitted_at, t.score, t.max_score, t.auto_submitted, t.focus_lost
               FROM course_registrations cr
               JOIN students s ON s.id = cr.student_id
               JOIN users u    ON u.id = s.user_id
          LEFT JOIN online_exam_attempts t ON t.exam_id = ? AND t.student_id = cr.student_id
              WHERE cr.offering_id = ? AND cr.status <> "dropped"
              ORDER BY t.id IS NULL, u.last_name, u.first_name',
            [$exam['id'], $exam['offering_id']]
        );
        $graded = array_filter($rows, static fn (array $r): bool => $r['status'] === 'graded');
        $pcts   = array_map(static fn (array $r): float => (float) $r['max_score'] > 0 ? (float) $r['score'] / (float) $r['max_score'] * 100 : 0.0, $graded);
        $stats  = [
            'registered' => count($rows),
            'sat'        => count(array_filter($rows, static fn (array $r): bool => $r['attempt_id'] !== null)),
            'in_progress'=> count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'in_progress')),
            'to_mark'    => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'submitted')),
            'graded'     => count($graded),
            'average'    => $pcts === [] ? null : round(array_sum($pcts) / count($pcts), 1),
            'highest'    => $pcts === [] ? null : round(max($pcts), 1),
            'lowest'     => $pcts === [] ? null : round(min($pcts), 1),
            'passed'     => count(array_filter($pcts, static fn (float $p): bool => $p >= (float) $exam['pass_percent'])),
        ];
        $exam = ExamService::find((int) $examId);

        if ($request->query('export') === 'csv') {
            Response::csv(array_map(static fn (array $r): array => [
                $r['admission_number'], $r['last_name'] . ', ' . $r['first_name'],
                $r['attempt_id'] === null ? 'not attempted' : $r['status'], $r['submitted_at'] ?? '',
                $r['score'] ?? '', $r['max_score'] ?? $exam['total_marks'],
                (float) ($r['max_score'] ?? 0) > 0 && $r['score'] !== null ? round((float) $r['score'] / (float) $r['max_score'] * 100, 1) : '',
            ], $rows), 'online-exam-' . $exam['id'] . '-results.csv',
                ['Student ID', 'Name', 'Status', 'Submitted', 'Score', 'Out of', 'Percent']);
        }

        return $this->view('lecturer.online-exams.results', [
            'pageTitle' => 'Results - ' . $exam['title'],
            'exam'      => $exam,
            'offering'  => $offering,
            'rows'      => $rows,
            'stats'     => $stats,
            'assessment'=> $exam['assessment_id'] ? Database::selectOne('SELECT * FROM assessments WHERE id = ?', [$exam['assessment_id']]) : null,
        ]);
    }

    /** One student's paper, for reading and marking. */
    public function attempt(Request $request, string $attemptId): string
    {
        $this->authorize('teaching.access');
        $attempt = Database::selectOne(
            'SELECT t.*, s.admission_number, u.first_name, u.last_name
               FROM online_exam_attempts t JOIN students s ON s.id = t.student_id JOIN users u ON u.id = s.user_id
              WHERE t.id = ?',
            [(int) $attemptId]
        );
        if ($attempt === null) {
            throw new HttpException(404);
        }
        $exam = $this->exam((int) $attempt['exam_id']);
        $attempt = ExamService::expireIfDue($attempt) + $attempt;
        return $this->view('lecturer.online-exams.attempt', [
            'pageTitle' => 'Marking - ' . $attempt['first_name'] . ' ' . $attempt['last_name'],
            'exam'      => $exam,
            'offering'  => CourseAccess::teaching((int) $exam['offering_id']),
            'attempt'   => $attempt,
            'questions' => ExamService::questions((int) $exam['id']),
            'answers'   => array_column(Database::select('SELECT * FROM online_exam_answers WHERE attempt_id = ?', [$attempt['id']]), null, 'question_id'),
        ]);
    }

    public function mark(Request $request, string $attemptId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $attempt = Database::selectOne('SELECT * FROM online_exam_attempts WHERE id = ?', [(int) $attemptId]);
        if ($attempt === null) {
            throw new HttpException(404);
        }
        $this->exam((int) $attempt['exam_id']);
        try {
            ExamService::mark((int) $attemptId, (array) $request->input('marks', []), (array) $request->input('feedback', []), (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/teaching/online-exams/attempts/' . $attemptId);
        }
        if ($request->input('next') === '1') {
            $next = Database::scalar(
                'SELECT id FROM online_exam_attempts WHERE exam_id = ? AND status = "submitted" AND id <> ? ORDER BY submitted_at LIMIT 1',
                [$attempt['exam_id'], $attempt['id']]
            );
            if ($next) {
                $this->success('Marks saved. Next paper to mark:', '/teaching/online-exams/attempts/' . $next);
            }
        }
        $this->success('Marks saved.', '/teaching/online-exams/' . $attempt['exam_id'] . '/results');
    }

    /** Copy the marks into the class gradebook as an assessment. */
    public function transfer(Request $request, string $examId): never
    {
        $this->authorize(['teaching.access', 'results.create'], true);
        $this->verifyCsrf($request);
        $exam   = $this->exam((int) $examId);
        $weight = max(0, min(100, (float) $request->input('weight', 10)));
        $counts = ExamService::transferToGradebook($exam, $weight, (int) Auth::id());
        Audit::log('transfer', 'elearning', 'online_exams', (string) $exam['id'],
            "Transferred online exam marks to the gradebook ({$counts['transferred']} students)");
        $message = "{$counts['transferred']} mark(s) transferred to the gradebook";
        $message .= $counts['missed'] > 0 ? ", {$counts['missed']} recorded as missed" : '';
        $message .= $counts['pending'] > 0 ? ". {$counts['pending']} still to sit or be marked - transfer again once done." : '.';
        $this->success($message, '/teaching/online-exams/' . $examId . '/results');
    }

    /* ---------------- Helpers ---------------- */

    /** The exam, with question count and total marks, if the user teaches its class. */
    private function exam(int $examId): array
    {
        $exam = ExamService::find($examId);
        if ($exam === null) {
            throw new HttpException(404, 'Exam not found.');
        }
        CourseAccess::teaching((int) $exam['offering_id']);
        return $exam;
    }

    private function hasAttempts(int $examId): bool
    {
        return (bool) Database::scalar('SELECT 1 FROM online_exam_attempts WHERE exam_id = ? LIMIT 1', [$examId]);
    }

    private function examData(Request $request, string $back): array
    {
        $data = $this->validate($request, [
            'title'            => 'required|max:150',
            'exam_type'        => 'required|in:' . implode(',', array_keys(ExamService::TYPES)),
            'opens_at'         => 'required',
            'closes_at'        => 'required',
            'duration_minutes' => 'required|integer',
            'pass_percent'     => 'required|integer',
            'show_results'     => 'required|in:' . implode(',', array_keys(ExamService::RESULT_POLICIES)),
            'instructions'     => 'nullable',
        ], ['opens_at' => 'Opens', 'closes_at' => 'Closes', 'duration_minutes' => 'Time allowed', 'pass_percent' => 'Pass mark']);

        $opens  = strtotime(str_replace('T', ' ', (string) $data['opens_at']));
        $closes = strtotime(str_replace('T', ' ', (string) $data['closes_at']));
        if ($opens === false || $closes === false) {
            $this->error('Enter valid opening and closing times.', $back);
        }
        if ($closes <= $opens) {
            $this->error('The exam must close after it opens.', $back);
        }
        $data['opens_at']            = date('Y-m-d H:i:s', $opens);
        $data['closes_at']           = date('Y-m-d H:i:s', $closes);
        $data['duration_minutes']    = max(1, min(600, (int) $data['duration_minutes']));
        $data['pass_percent']        = max(0, min(100, (int) $data['pass_percent']));
        $data['instructions']        = trim((string) ($data['instructions'] ?? '')) ?: null;
        $data['shuffle_questions']   = $request->input('shuffle_questions') === '1' ? 1 : 0;
        $data['shuffle_options']     = $request->input('shuffle_options') === '1' ? 1 : 0;
        $data['requires_enrollment'] = $request->input('requires_enrollment') === '1' ? 1 : 0;
        return $data;
    }
}
