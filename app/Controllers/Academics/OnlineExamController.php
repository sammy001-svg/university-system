<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Elearning\CourseAccess;
use App\Services\Elearning\ExamService;
use App\Services\Elearning\Oversight;

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
            'canTeach'    => Auth::can('teaching.access') && CourseAccess::mayTeach((int) $exam['offering_id']),
        ]);
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

