<?php
declare(strict_types=1);

namespace App\Services\Elearning;

use App\Core\Audit;
use App\Core\Database;
use App\Models\SemesterEnrollment;
use App\Services\Alerts\Alerts;
use RuntimeException;

/**
 * The online examination engine: availability, attempts, timing, marking and
 * the transfer of scores to the course gradebook.
 *
 * All times are compared in PHP (the application's time zone), never with the
 * database clock, so a server whose MySQL zone differs cannot open an exam
 * early or close it late. The deadline is enforced on the server; the
 * countdown in the browser is only a display.
 */
final class ExamService
{
    /** Answers arriving this many seconds after the deadline (network delay) are still accepted. */
    public const GRACE_SECONDS = 30;

    public const TYPES = [
        'quiz' => 'Quiz', 'test' => 'Class test', 'midterm' => 'Mid-term examination',
        'final' => 'Final examination', 'assignment' => 'Online assignment',
    ];
    public const QUESTION_TYPES = [
        'single' => 'Multiple choice (one answer)', 'multiple' => 'Multiple choice (several answers)',
        'true_false' => 'True / False', 'short_answer' => 'Short answer', 'essay' => 'Essay / long answer',
    ];
    public const RESULT_POLICIES = [
        'after_close' => 'When the exam closes', 'immediately' => 'As soon as the student submits',
        'manual' => 'When I release them',
    ];

    public const EXAM_RULES = [
        'title'            => 'required|max:150',
        'exam_type'        => 'required|in:quiz,test,midterm,final,assignment',
        'opens_at'         => 'required',
        'closes_at'        => 'required',
        'duration_minutes' => 'required|integer',
        'pass_percent'     => 'required|integer',
        'show_results'     => 'required|in:after_close,immediately,manual',
        'instructions'     => 'nullable',
    ];

    public const EXAM_LABELS = [
        'opens_at'         => 'Opens',
        'closes_at'        => 'Closes',
        'duration_minutes' => 'Time allowed',
        'pass_percent'     => 'Pass mark',
    ];

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Validate and normalise online exam scheduling/settings fields.
     * @throws RuntimeException when opening/closing times are invalid or inverted
     */
    public static function normalizeExamInput(array $data, array $flags): array
    {
        $opens  = strtotime(str_replace('T', ' ', (string) ($data['opens_at'] ?? '')));
        $closes = strtotime(str_replace('T', ' ', (string) ($data['closes_at'] ?? '')));
        if ($opens === false || $closes === false) {
            throw new RuntimeException('Enter valid opening and closing times.');
        }
        if ($closes <= $opens) {
            throw new RuntimeException('The exam must close after it opens.');
        }
        $data['opens_at']            = date('Y-m-d H:i:s', $opens);
        $data['closes_at']           = date('Y-m-d H:i:s', $closes);
        $data['duration_minutes']    = max(1, min(600, (int) ($data['duration_minutes'] ?? 30)));
        $data['pass_percent']        = max(0, min(100, (int) ($data['pass_percent'] ?? 50)));
        $data['instructions']        = trim((string) ($data['instructions'] ?? '')) ?: null;
        $data['shuffle_questions']   = ($flags['shuffle_questions'] ?? null) === '1' ? 1 : 0;
        $data['shuffle_options']     = ($flags['shuffle_options'] ?? null) === '1' ? 1 : 0;
        $data['requires_enrollment'] = ($flags['requires_enrollment'] ?? null) === '1' ? 1 : 0;
        return $data;
    }

    public static function createExam(array $offering, array $data, int $userId, bool $notifyLecturers = false): int
    {
        $offeringId = (int) $offering['id'];
        Database::statement(
            'INSERT INTO online_exams (offering_id, title, exam_type, instructions, opens_at, closes_at, duration_minutes, pass_percent,
                                       shuffle_questions, shuffle_options, requires_enrollment, show_results, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "draft", ?)',
            [$offeringId, $data['title'], $data['exam_type'], $data['instructions'], $data['opens_at'], $data['closes_at'],
             $data['duration_minutes'], $data['pass_percent'], $data['shuffle_questions'], $data['shuffle_options'],
             $data['requires_enrollment'], $data['show_results'], $userId]
        );
        $examId = Database::lastInsertId();
        Audit::log('create', 'elearning', 'online_exams', (string) $examId, "Created online exam {$data['title']} for {$offering['code']}");
        if ($notifyLecturers) {
            $teachers = array_values(array_diff(Oversight::offeringTeachersUserIds($offeringId), [$userId]));
            Alerts::sendMany($teachers, 'exam_published', 'Online exam scheduled: ' . $offering['code'] . ' - ' . $data['title'],
                'Scheduled for ' . fdatetime($data['opens_at']) . ' to ' . fdatetime($data['closes_at']) . '. Add questions and publish when ready.',
                '/teaching/online-exams/' . $examId, 'info', 'clipboard');
        }
        return $examId;
    }

    public static function updateExam(array $exam, array $data): void
    {
        if (self::hasAttempts((int) $exam['id']) && (int) $data['duration_minutes'] !== (int) $exam['duration_minutes']) {
            throw new RuntimeException('Students have already started this exam, so its duration can no longer change.');
        }
        Database::statement(
            'UPDATE online_exams SET title = ?, exam_type = ?, instructions = ?, opens_at = ?, closes_at = ?, duration_minutes = ?,
                    pass_percent = ?, shuffle_questions = ?, shuffle_options = ?, requires_enrollment = ?, show_results = ?,
                    status = IF(status = "closed" AND ? > ?, "published", status)
              WHERE id = ?',
            [$data['title'], $data['exam_type'], $data['instructions'], $data['opens_at'], $data['closes_at'], $data['duration_minutes'],
             $data['pass_percent'], $data['shuffle_questions'], $data['shuffle_options'], $data['requires_enrollment'],
             $data['show_results'], $data['closes_at'], self::now(), $exam['id']]
        );
        Audit::log('update', 'elearning', 'online_exams', (string) $exam['id'], "Updated online exam {$data['title']}");
    }

    public static function hasAttempts(int $examId): bool
    {
        return (bool) Database::scalar('SELECT 1 FROM online_exam_attempts WHERE exam_id = ? LIMIT 1', [$examId]);
    }

    public static function publish(array $exam, array $offering): void
    {
        $problems = self::publishProblems($exam);
        if ($problems !== []) {
            throw new RuntimeException(implode(' ', $problems));
        }
        Database::statement('UPDATE online_exams SET status = "published" WHERE id = ?', [$exam['id']]);
        ClassroomService::notifyClass(
            (int) $exam['offering_id'],
            'Online exam: ' . $offering['code'] . ' - ' . $exam['title'],
            'Opens ' . fdatetime($exam['opens_at']) . ', closes ' . fdatetime($exam['closes_at']) . '. Time allowed: '
                . $exam['duration_minutes'] . ' minutes.',
            '/portal/exams/' . $exam['id'],
            'exam_published'
        );
        Audit::log('publish', 'elearning', 'online_exams', (string) $exam['id'], 'Published online exam ' . $exam['title']);
    }

    public static function unpublish(int $examId): void
    {
        if (self::hasAttempts($examId)) {
            throw new RuntimeException('Students have already started this exam; close it instead.');
        }
        Database::statement('UPDATE online_exams SET status = "draft" WHERE id = ?', [$examId]);
    }

    public static function setResultsReleased(array $exam, array $offering, bool $released): void
    {
        Database::statement('UPDATE online_exams SET results_released = ? WHERE id = ?', [$released ? 1 : 0, $exam['id']]);
        if ($released) {
            Alerts::sendMany(
                array_column(Database::select(
                    'SELECT s.user_id FROM online_exam_attempts t JOIN students s ON s.id = t.student_id WHERE t.exam_id = ? AND t.status = "graded"',
                    [$exam['id']]
                ), 'user_id'),
                'results_released',
                'Result available: ' . $offering['code'] . ' - ' . $exam['title'],
                'Your marked paper is ready to view.',
                '/portal/exams/' . $exam['id'],
                'success',
                'chart'
            );
        }
    }

    public static function deleteExam(array $exam): void
    {
        if (self::hasAttempts((int) $exam['id'])) {
            throw new RuntimeException('Students have taken this exam, so it cannot be deleted. Close it instead.');
        }
        Database::statement('DELETE FROM online_exams WHERE id = ?', [$exam['id']]);
        Audit::log('delete', 'elearning', 'online_exams', (string) $exam['id'], 'Deleted online exam ' . $exam['title']);
    }

    /* ------------------------------------------------------------------ */
    /*  Exams and questions                                                */
    /* ------------------------------------------------------------------ */

    public static function find(int $examId): ?array
    {
        return Database::selectOne(
            'SELECT e.*, (SELECT COUNT(*) FROM online_exam_questions q WHERE q.exam_id = e.id) AS question_count,
                    (SELECT COALESCE(SUM(q.marks), 0) FROM online_exam_questions q WHERE q.exam_id = e.id) AS total_marks
               FROM online_exams e WHERE e.id = ?',
            [$examId]
        );
    }

    /** draft | upcoming | open | closed - from the status and the time window. */
    public static function phase(array $exam): string
    {
        if ($exam['status'] === 'draft') {
            return 'draft';
        }
        $now = self::now();
        if ($exam['status'] === 'closed' || $now > $exam['closes_at']) {
            return 'closed';
        }
        return $now < $exam['opens_at'] ? 'upcoming' : 'open';
    }

    /** Questions with their options, in authoring order. */
    public static function questions(int $examId): array
    {
        $questions = Database::select('SELECT * FROM online_exam_questions WHERE exam_id = ? ORDER BY sort_order, id', [$examId]);
        if ($questions === []) {
            return [];
        }
        $options = [];
        foreach (Database::select(
            'SELECT o.* FROM online_exam_options o JOIN online_exam_questions q ON q.id = o.question_id
              WHERE q.exam_id = ? ORDER BY o.sort_order, o.id',
            [$examId]
        ) as $option) {
            $options[(int) $option['question_id']][] = $option;
        }
        foreach ($questions as &$question) {
            $question['options'] = $options[(int) $question['id']] ?? [];
        }
        return $questions;
    }

    /**
     * Save a question and its options from the authoring form.
     * $options: list of ['body' => string, 'correct' => bool]; for short answers, the accepted answers.
     */
    public static function saveQuestion(int $examId, ?int $questionId, string $type, string $body, float $marks,
                                        ?string $explanation, array $options): int
    {
        if (!isset(self::QUESTION_TYPES[$type])) {
            throw new RuntimeException('Unknown question type.');
        }
        if (trim($body) === '') {
            throw new RuntimeException('Enter the question.');
        }
        if ($marks <= 0) {
            throw new RuntimeException('Marks must be more than zero.');
        }
        if ($type === 'true_false') {
            $correct = ($options[0]['body'] ?? 'true') === 'false' ? 'false' : 'true';
            $options = [['body' => 'True', 'correct' => $correct === 'true'], ['body' => 'False', 'correct' => $correct === 'false']];
        }
        $options = array_values(array_filter($options, static fn (array $o): bool => trim((string) $o['body']) !== ''));
        $correct = count(array_filter($options, static fn (array $o): bool => (bool) $o['correct']));
        if (in_array($type, ['single', 'multiple'], true)) {
            if (count($options) < 2) {
                throw new RuntimeException('Give at least two choices.');
            }
            if ($correct === 0) {
                throw new RuntimeException('Mark the correct answer.');
            }
            if ($type === 'single' && $correct > 1) {
                throw new RuntimeException('A one-answer question can have only one correct choice; use "several answers" instead.');
            }
        }
        if ($type === 'short_answer') {
            if ($options === []) {
                throw new RuntimeException('Give at least one accepted answer.');
            }
            $options = array_map(static fn (array $o): array => ['body' => $o['body'], 'correct' => true], $options);
        }
        if ($type === 'essay') {
            $options = [];
        }

        return Database::transaction(static function () use ($examId, $questionId, $type, $body, $marks, $explanation, $options): int {
            if ($questionId === null) {
                $next = (int) Database::scalar('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM online_exam_questions WHERE exam_id = ?', [$examId]);
                Database::statement(
                    'INSERT INTO online_exam_questions (exam_id, question_type, body, marks, explanation, sort_order) VALUES (?, ?, ?, ?, ?, ?)',
                    [$examId, $type, $body, $marks, $explanation, $next]
                );
                $questionId = Database::lastInsertId();
            } else {
                Database::statement(
                    'UPDATE online_exam_questions SET question_type = ?, body = ?, marks = ?, explanation = ? WHERE id = ? AND exam_id = ?',
                    [$type, $body, $marks, $explanation, $questionId, $examId]
                );
                Database::statement('DELETE FROM online_exam_options WHERE question_id = ?', [$questionId]);
            }
            foreach ($options as $i => $option) {
                Database::statement(
                    'INSERT INTO online_exam_options (question_id, body, is_correct, sort_order) VALUES (?, ?, ?, ?)',
                    [$questionId, trim((string) $option['body']), $option['correct'] ? 1 : 0, $i + 1]
                );
            }
            return $questionId;
        });
    }

    /** Problems that stop an exam being published; empty when it is ready. */
    public static function publishProblems(array $exam): array
    {
        $problems = [];
        if ((int) $exam['question_count'] === 0) {
            $problems[] = 'Add at least one question.';
        }
        if ($exam['closes_at'] <= $exam['opens_at']) {
            $problems[] = 'The exam must close after it opens.';
        }
        if ($exam['closes_at'] <= self::now()) {
            $problems[] = 'The closing time has already passed.';
        }
        return $problems;
    }

    /* ------------------------------------------------------------------ */
    /*  Taking an exam                                                     */
    /* ------------------------------------------------------------------ */

    public static function attempt(int $examId, int $studentId): ?array
    {
        return Database::selectOne('SELECT * FROM online_exam_attempts WHERE exam_id = ? AND student_id = ?', [$examId, $studentId]);
    }

    /** Why the student cannot start the exam now, or null when they can. */
    public static function cannotStart(array $exam, int $studentId): ?string
    {
        $phase = self::phase($exam);
        if ($phase === 'draft') {
            return 'This exam has not been published.';
        }
        if ($phase === 'upcoming') {
            return 'This exam opens on ' . fdatetime($exam['opens_at']) . '.';
        }
        if ($phase === 'closed') {
            return 'This exam closed on ' . fdatetime($exam['closes_at']) . '.';
        }
        if ((int) $exam['question_count'] === 0) {
            return 'This exam has no questions yet.';
        }
        if ((int) $exam['requires_enrollment'] === 1) {
            $semesterId = (int) Database::scalar('SELECT semester_id FROM course_offerings WHERE id = ?', [$exam['offering_id']]);
            if (!(new SemesterEnrollment())->isEnrolled($studentId, $semesterId)) {
                return 'Your registration for this semester is not complete. Pay your 1st installment to sit this exam.';
            }
        }
        return null;
    }

    /** Begin (or resume) the student's attempt. */
    public static function start(array $exam, int $studentId, string $ip, string $agent): array
    {
        $existing = self::attempt((int) $exam['id'], $studentId);
        if ($existing !== null) {
            return $existing;
        }
        $reason = self::cannotStart($exam, $studentId);
        if ($reason !== null) {
            throw new RuntimeException($reason);
        }

        // The order this student sees: questions and choices shuffled once and kept, so a reload never reshuffles.
        $order = ['questions' => [], 'options' => []];
        foreach (self::questions((int) $exam['id']) as $question) {
            $order['questions'][] = (int) $question['id'];
            $ids = array_map('intval', array_column($question['options'], 'id'));
            if ((int) $exam['shuffle_options'] === 1 && $question['question_type'] !== 'true_false') {
                shuffle($ids);
            }
            $order['options'][(string) $question['id']] = $ids;
        }
        if ((int) $exam['shuffle_questions'] === 1) {
            shuffle($order['questions']);
        }

        $started  = time();
        $deadline = min($started + (int) $exam['duration_minutes'] * 60, strtotime((string) $exam['closes_at']));
        Database::statement(
            'INSERT IGNORE INTO online_exam_attempts (exam_id, student_id, started_at, deadline_at, status, question_order, max_score, ip_address, user_agent)
             VALUES (?, ?, ?, ?, "in_progress", ?, ?, ?, ?)',
            [$exam['id'], $studentId, date('Y-m-d H:i:s', $started), date('Y-m-d H:i:s', $deadline),
             json_encode($order), $exam['total_marks'], $ip, $agent]
        );
        return self::attempt((int) $exam['id'], $studentId);
    }

    public static function secondsLeft(array $attempt): int
    {
        return max(0, strtotime((string) $attempt['deadline_at']) - time());
    }

    /** Submit attempts whose time has run out (the student closed the browser, lost power ...). */
    public static function expireIfDue(array $attempt): array
    {
        if ($attempt['status'] === 'in_progress' && time() > strtotime((string) $attempt['deadline_at']) + self::GRACE_SECONDS) {
            self::submit((int) $attempt['id'], true);
            return Database::selectOne('SELECT * FROM online_exam_attempts WHERE id = ?', [$attempt['id']]);
        }
        return $attempt;
    }

    public static function expireDueAttempts(int $examId): void
    {
        foreach (Database::select('SELECT * FROM online_exam_attempts WHERE exam_id = ? AND status = "in_progress"', [$examId]) as $attempt) {
            self::expireIfDue($attempt);
        }
    }

    /** Save one answer while the attempt is running. */
    public static function saveAnswer(array $attempt, int $questionId, array $optionIds, ?string $text): void
    {
        if ($attempt['status'] !== 'in_progress') {
            throw new RuntimeException('This attempt has already been submitted.');
        }
        if (time() > strtotime((string) $attempt['deadline_at']) + self::GRACE_SECONDS) {
            throw new RuntimeException('Time is up.');
        }
        $question = Database::selectOne(
            'SELECT id, question_type FROM online_exam_questions WHERE id = ? AND exam_id = ?',
            [$questionId, $attempt['exam_id']]
        );
        if ($question === null) {
            throw new RuntimeException('Unknown question.');
        }
        $valid = array_map('intval', array_column(Database::select('SELECT id FROM online_exam_options WHERE question_id = ?', [$questionId]), 'id'));
        $optionIds = array_values(array_intersect(array_map('intval', $optionIds), $valid));
        if (in_array($question['question_type'], ['single', 'true_false'], true)) {
            $optionIds = array_slice($optionIds, 0, 1);
        }
        $isText = in_array($question['question_type'], ['short_answer', 'essay'], true);
        Database::statement(
            'INSERT INTO online_exam_answers (attempt_id, question_id, option_ids, answer_text, answered_at) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE option_ids = VALUES(option_ids), answer_text = VALUES(answer_text), answered_at = VALUES(answered_at)',
            [$attempt['id'], $questionId, $isText ? null : implode(',', $optionIds),
             $isText ? mb_substr((string) $text, 0, 20000) : null, self::now()]
        );
    }

    /** Close the attempt and mark everything that can be marked automatically. */
    public static function submit(int $attemptId, bool $auto = false): void
    {
        Database::transaction(static function () use ($attemptId, $auto): void {
            $attempt = Database::selectOne('SELECT * FROM online_exam_attempts WHERE id = ? FOR UPDATE', [$attemptId]);
            if ($attempt === null || $attempt['status'] !== 'in_progress') {
                return;
            }
            Database::statement(
                'UPDATE online_exam_attempts SET status = "submitted", submitted_at = ?, auto_submitted = ? WHERE id = ?',
                [$auto ? min(self::now(), date('Y-m-d H:i:s', strtotime((string) $attempt['deadline_at']) + self::GRACE_SECONDS)) : self::now(),
                 $auto ? 1 : 0, $attemptId]
            );
            self::autoMark($attempt);
            self::recalculate($attemptId);
        });
    }

    private static function autoMark(array $attempt): void
    {
        $answers = array_column(Database::select('SELECT * FROM online_exam_answers WHERE attempt_id = ?', [$attempt['id']]), null, 'question_id');
        foreach (self::questions((int) $attempt['exam_id']) as $question) {
            $answer = $answers[$question['id']] ?? null;
            $marks  = (float) $question['marks'];
            $chosen = $answer !== null && $answer['option_ids'] !== null && $answer['option_ids'] !== ''
                ? array_map('intval', explode(',', (string) $answer['option_ids'])) : [];
            $text   = trim((string) ($answer['answer_text'] ?? ''));
            $right  = array_map('intval', array_column(array_filter($question['options'], static fn (array $o): bool => (int) $o['is_correct'] === 1), 'id'));

            switch ($question['question_type']) {
                case 'single':
                case 'true_false':
                case 'multiple':
                    sort($chosen);
                    sort($right);
                    $correct = $chosen !== [] && $chosen === $right;
                    [$isCorrect, $awarded] = [$correct ? 1 : 0, $correct ? $marks : 0.0];
                    break;
                case 'short_answer':
                    $accepted = array_map([self::class, 'normalise'], array_column($question['options'], 'body'));
                    $correct  = $text !== '' && in_array(self::normalise($text), $accepted, true);
                    [$isCorrect, $awarded] = [$correct ? 1 : 0, $correct ? $marks : 0.0];
                    break;
                default: // essay: marked by the lecturer; a blank answer scores nothing.
                    [$isCorrect, $awarded] = [null, $text === '' ? 0.0 : null];
            }
            Database::statement(
                'INSERT INTO online_exam_answers (attempt_id, question_id, is_correct, marks_awarded) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE is_correct = VALUES(is_correct), marks_awarded = VALUES(marks_awarded)',
                [$attempt['id'], $question['id'], $isCorrect, $awarded]
            );
        }
    }

    /** Total the marks; an attempt is "graded" once nothing is waiting for manual marking. */
    public static function recalculate(int $attemptId): void
    {
        $row = Database::selectOne(
            'SELECT COALESCE(SUM(a.marks_awarded), 0) AS score, SUM(a.marks_awarded IS NULL) AS pending,
                    (SELECT COALESCE(SUM(q.marks), 0) FROM online_exam_questions q WHERE q.exam_id = t.exam_id) AS max_score
               FROM online_exam_attempts t LEFT JOIN online_exam_answers a ON a.attempt_id = t.id
              WHERE t.id = ? GROUP BY t.id',
            [$attemptId]
        );
        Database::statement(
            'UPDATE online_exam_attempts SET score = ?, max_score = ?,
                    status = IF(status = "in_progress", status, IF(? > 0, "submitted", "graded")) WHERE id = ?',
            [$row['score'], $row['max_score'], (int) $row['pending'], $attemptId]
        );
    }

    /** The lecturer's marks for written answers (and overrides of automatic ones). */
    public static function mark(int $attemptId, array $marks, array $feedback, int $graderId): void
    {
        Database::transaction(static function () use ($attemptId, $marks, $feedback, $graderId): void {
            $attempt = Database::selectOne('SELECT * FROM online_exam_attempts WHERE id = ?', [$attemptId]);
            if ($attempt === null || $attempt['status'] === 'in_progress') {
                throw new RuntimeException('This attempt has not been submitted yet.');
            }
            $max = array_column(Database::select('SELECT id, marks FROM online_exam_questions WHERE exam_id = ?', [$attempt['exam_id']]), 'marks', 'id');
            foreach ($marks as $questionId => $value) {
                if (!isset($max[$questionId]) || $value === '' || $value === null) {
                    continue;
                }
                $value = max(0, min((float) $max[$questionId], (float) $value));
                Database::statement(
                    'INSERT INTO online_exam_answers (attempt_id, question_id, marks_awarded, feedback, graded_by) VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE marks_awarded = VALUES(marks_awarded), feedback = VALUES(feedback), graded_by = VALUES(graded_by)',
                    [$attemptId, $questionId, $value, trim((string) ($feedback[$questionId] ?? '')) ?: null, $graderId]
                );
            }
            self::recalculate($attemptId);
        });
    }

    /** May the student see their mark and the marked paper? */
    public static function resultsVisible(array $exam, array $attempt): bool
    {
        if ($attempt['status'] === 'in_progress') {
            return false;
        }
        return match ($exam['show_results']) {
            'immediately' => true,
            'after_close' => self::phase($exam) === 'closed',
            default       => (int) $exam['results_released'] === 1,
        };
    }

    /** Close the exam now and submit any attempt still open. */
    public static function close(int $examId): void
    {
        Database::statement('UPDATE online_exams SET status = "closed" WHERE id = ?', [$examId]);
        foreach (Database::select('SELECT id FROM online_exam_attempts WHERE exam_id = ? AND status = "in_progress"', [$examId]) as $row) {
            self::submit((int) $row['id'], true);
        }
    }

    /**
     * Copy the marks into the course gradebook as an assessment (created on the first transfer),
     * so they count towards the final course result like any other coursework.
     * @return array{assessment_id:int, transferred:int, missed:int, pending:int}
     */
    public static function transferToGradebook(array $exam, float $weight, int $userId): array
    {
        return Database::transaction(static function () use ($exam, $weight, $userId): array {
            $assessmentId = (int) ($exam['assessment_id'] ?? 0);
            $type =['quiz' => 'quiz', 'test' => 'cat', 'midterm' => 'cat', 'final' => 'final_exam', 'assignment' => 'assignment'][$exam['exam_type']];
            if ($assessmentId === 0 || !Database::scalar('SELECT 1 FROM assessments WHERE id = ?', [$assessmentId])) {
                Database::statement(
                    'INSERT INTO assessments (offering_id, title, type, max_score, weight, due_date, instructions, is_published, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)',
                    [$exam['offering_id'], $exam['title'], $type, $exam['total_marks'], $weight, $exam['closes_at'],
                     'Online examination - marks transferred from the online exam.', $userId]
                );
                $assessmentId = Database::lastInsertId();
                Database::statement('UPDATE online_exams SET assessment_id = ? WHERE id = ?', [$assessmentId, $exam['id']]);
            } else {
                Database::statement('UPDATE assessments SET max_score = ?, weight = ? WHERE id = ?', [$exam['total_marks'], $weight, $assessmentId]);
            }

            $now = self::now();
            $counts = ['assessment_id' => $assessmentId, 'transferred' => 0, 'missed' => 0, 'pending' => 0];
            $students = Database::select(
                'SELECT cr.student_id, t.status, t.score, t.submitted_at
                   FROM course_registrations cr
              LEFT JOIN online_exam_attempts t ON t.student_id = cr.student_id AND t.exam_id = ?
                  WHERE cr.offering_id = ? AND cr.status <> "dropped"',
                [$exam['id'], $exam['offering_id']]
            );
            foreach ($students as $s) {
                if ($s['status'] === 'graded') {
                    Database::statement(
                        'INSERT INTO assessment_scores (assessment_id, student_id, score, submitted_at, graded_by, graded_at, status)
                         VALUES (?, ?, ?, ?, ?, ?, "graded")
                         ON DUPLICATE KEY UPDATE score = VALUES(score), submitted_at = VALUES(submitted_at), graded_by = VALUES(graded_by),
                                                 graded_at = VALUES(graded_at), status = "graded"',
                        [$assessmentId, $s['student_id'], $s['score'], $s['submitted_at'], $userId, $now]
                    );
                    $counts['transferred']++;
                } elseif ($s['status'] === null && self::phase($exam) === 'closed') {
                    Database::statement(
                        'INSERT INTO assessment_scores (assessment_id, student_id, score, graded_by, graded_at, status)
                         VALUES (?, ?, 0, ?, ?, "missed")
                         ON DUPLICATE KEY UPDATE score = IF(status = "graded", score, 0), status = IF(status = "graded", status, "missed")',
                        [$assessmentId, $s['student_id'], $userId, $now]
                    );
                    $counts['missed']++;
                } else {
                    $counts['pending']++;
                }
            }
            return $counts;
        });
    }

    /** Lower-case, single-spaced, without surrounding punctuation: "  Paris. " matches "paris". */
    private static function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        return trim($text, " \t.,;:!?\"'");
    }
}
