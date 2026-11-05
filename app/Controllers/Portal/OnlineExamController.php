<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Elearning\CourseAccess;
use App\Services\Elearning\ExamService;
use RuntimeException;

/** Sitting online examinations: the list, the exam lobby, the timed paper and the marked result. */
final class OnlineExamController extends Controller
{
    use CurrentStudent;

    public function index(Request $request): string
    {
        $student = $this->student();
        $ids     = CourseAccess::studiedOfferingIds((int) $student['id']);
        $groups  = ['open' => [], 'upcoming' => [], 'done' => []];
        foreach (Database::select(
            'SELECT e.id, c.code, c.title AS course_title
               FROM online_exams e JOIN course_offerings o ON o.id = e.offering_id JOIN courses c ON c.id = o.course_id
              WHERE e.offering_id IN (' . CourseAccess::inList($ids) . ') AND e.status <> "draft"
              ORDER BY e.opens_at'
        ) as $row) {
            $exam    = ExamService::find((int) $row['id']) + $row;
            $attempt = ExamService::attempt((int) $exam['id'], (int) $student['id']);
            $attempt = $attempt === null ? null : ExamService::expireIfDue($attempt);
            $exam['phase']   = ExamService::phase($exam);
            $exam['attempt'] = $attempt;
            $exam['visible'] = $attempt !== null && ExamService::resultsVisible($exam, $attempt);

            if (($attempt !== null && $attempt['status'] !== 'in_progress') || $exam['phase'] === 'closed') {
                $groups['done'][] = $exam;
            } elseif ($exam['phase'] === 'open') {
                $groups['open'][] = $exam;
            } else {
                $groups['upcoming'][] = $exam;
            }
        }
        $groups['done'] = array_reverse($groups['done']);

        return $this->view('portal.exams.index', ['pageTitle' => 'Online Examinations', 'student' => $student, 'groups' => $groups]);
    }

    /** The exam's details and rules, its start button - and afterwards, the result. */
    public function show(Request $request, string $id): string
    {
        [$exam, $offering, $student] = $this->load((int) $id);
        $attempt = ExamService::attempt((int) $exam['id'], (int) $student['id']);
        $attempt = $attempt === null ? null : ExamService::expireIfDue($attempt);
        $visible = $attempt !== null && ExamService::resultsVisible($exam, $attempt);

        return $this->view('portal.exams.show', [
            'pageTitle' => $exam['title'],
            'exam'      => $exam,
            'offering'  => $offering,
            'phase'     => ExamService::phase($exam),
            'attempt'   => $attempt,
            'blocked'   => $attempt === null ? ExamService::cannotStart($exam, (int) $student['id']) : null,
            'visible'   => $visible,
            'questions' => $visible ? ExamService::questions((int) $exam['id']) : [],
            'answers'   => $visible ? array_column(Database::select('SELECT * FROM online_exam_answers WHERE attempt_id = ?', [$attempt['id']]), null, 'question_id') : [],
        ]);
    }

    public function start(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        [$exam, , $student] = $this->load((int) $id);
        if ($request->input('agree') !== '1') {
            $this->error('Confirm that you have read the instructions and will work on your own.', '/portal/exams/' . $id);
        }
        try {
            ExamService::start($exam, (int) $student['id'], $request->ip(), $request->userAgent());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/portal/exams/' . $id);
        }
        $this->redirect('/portal/exams/' . $id . '/take');
    }

    /** The timed exam paper. */
    public function take(Request $request, string $id): string
    {
        [$exam, $offering, $student] = $this->load((int) $id);
        $attempt = ExamService::attempt((int) $exam['id'], (int) $student['id']);
        if ($attempt === null) {
            $this->redirect('/portal/exams/' . $id);
        }
        $attempt = ExamService::expireIfDue($attempt);
        if ($attempt['status'] !== 'in_progress') {
            $this->warning('Your exam has been submitted.', '/portal/exams/' . $id);
        }

        // Questions and choices in the order fixed for this student; correct answers never reach the browser.
        $byId  = array_column(ExamService::questions((int) $exam['id']), null, 'id');
        $order = json_decode((string) $attempt['question_order'], true) ?: ['questions' => array_keys($byId), 'options' => []];
        $paper = [];
        foreach ($order['questions'] as $questionId) {
            if (!isset($byId[$questionId])) {
                continue;
            }
            $q       = $byId[$questionId];
            $options = array_column($q['options'], null, 'id');
            $sorted  = [];
            foreach ($order['options'][(string) $questionId] ?? array_keys($options) as $optionId) {
                if (isset($options[$optionId])) {
                    $sorted[] = ['id' => (int) $optionId, 'body' => $options[$optionId]['body']];
                }
            }
            $paper[] = ['id' => (int) $q['id'], 'type' => $q['question_type'], 'body' => $q['body'], 'marks' => (float) $q['marks'],
                        'options' => $q['question_type'] === 'short_answer' ? [] : $sorted];
        }

        return $this->view('portal.exams.take', [
            'pageTitle'   => $exam['title'],
            'exam'        => $exam,
            'offering'    => $offering,
            'student'     => $student,
            'attempt'     => $attempt,
            'paper'       => $paper,
            'answers'     => array_column(Database::select('SELECT question_id, option_ids, answer_text FROM online_exam_answers WHERE attempt_id = ?', [$attempt['id']]), null, 'question_id'),
            'secondsLeft' => ExamService::secondsLeft($attempt),
        ]);
    }

    /** Autosave of one answer (JSON). */
    public function answer(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        [$exam, , $student] = $this->load((int) $id);
        $attempt = ExamService::attempt((int) $exam['id'], (int) $student['id']);
        if ($attempt === null) {
            Response::json(['ok' => false, 'message' => 'Start the exam first.'], 409);
        }
        try {
            ExamService::saveAnswer($attempt, (int) $request->input('question_id'), (array) $request->input('option_ids', []),
                $request->raw('answer_text') !== null ? (string) $request->raw('answer_text') : null);
        } catch (RuntimeException $e) {
            Response::json(['ok' => false, 'message' => $e->getMessage(), 'seconds_left' => ExamService::secondsLeft($attempt)], 409);
        }
        Response::json(['ok' => true, 'saved_at' => date('H:i:s'), 'seconds_left' => ExamService::secondsLeft($attempt)]);
    }

    /** The student left the exam window (another tab, another program): logged for the lecturer. */
    public function focus(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        [$exam, , $student] = $this->load((int) $id);
        Database::statement(
            'UPDATE online_exam_attempts SET focus_lost = focus_lost + 1 WHERE exam_id = ? AND student_id = ? AND status = "in_progress"',
            [$exam['id'], $student['id']]
        );
        Response::json(['ok' => true]);
    }

    public function submit(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        [$exam, , $student] = $this->load((int) $id);
        $attempt = ExamService::attempt((int) $exam['id'], (int) $student['id']);
        if ($attempt !== null && $attempt['status'] === 'in_progress') {
            ExamService::submit((int) $attempt['id'], $request->input('auto') === '1');
        }
        $this->success('Your exam has been submitted. Well done!', '/portal/exams/' . $id);
    }

    /** @return array{0:array,1:array,2:array} exam, offering, student */
    private function load(int $examId): array
    {
        $student = $this->student();
        $exam    = ExamService::find($examId);
        if ($exam === null || $exam['status'] === 'draft') {
            throw new HttpException(404, 'Exam not found.');
        }
        $offering = CourseAccess::studying((int) $exam['offering_id'], (int) $student['id']);
        return [$exam, $offering, $student];
    }
}
