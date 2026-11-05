<?php
declare(strict_types=1);

namespace App\Controllers\Quality;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Alerts\Alerts;
use App\Support\Lookup;

/**
 * End-of-semester course and lecturer evaluation. Students rate each course they are
 * registered on (1-5 per statement, plus comments); answers are stored without the
 * student's identity, and results only appear once a course has the minimum number of
 * responses. Quality assurance sees everything; lecturers see their own courses.
 */
final class EvaluationController extends Controller
{
    public const SCALE = [1 => 'Strongly disagree', 2 => 'Disagree', 3 => 'Neutral', 4 => 'Agree', 5 => 'Strongly agree'];

    public function index(Request $request): string
    {
        $this->authorize('evaluations.view');
        $periods = Database::select(
            'SELECT e.*, CONCAT(ay.name, " ", sem.name) AS semester_label,
                    (SELECT COUNT(*) FROM evaluation_responses r WHERE r.period_id = e.id) AS responses
               FROM evaluation_periods e JOIN semesters sem ON sem.id = e.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
              ORDER BY e.opens_at DESC'
        );
        $periodId = (int) $request->query('period', $periods[0]['id'] ?? 0);
        return $this->view('evaluations.index', [
            'pageTitle' => 'Course Evaluations',
            'periods'   => $periods,
            'period'    => $periodId ? Database::selectOne('SELECT * FROM evaluation_periods WHERE id = ?', [$periodId]) : null,
            'rows'      => $periodId ? $this->summary($periodId, null) : [],
            'semesters' => Lookup::semesters(),
            'canManage' => Auth::can('evaluations.manage'),
        ]);
    }

    public function storePeriod(Request $request): never
    {
        $this->authorize('evaluations.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['semester_id' => 'required|integer', 'title' => 'required|max:150', 'opens_at' => 'required', 'closes_at' => 'required',
                                           'min_responses' => 'nullable|integer']);
        $opens = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', (string) $data['opens_at'])));
        $closes = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', (string) $data['closes_at'])));
        if ($closes <= $opens) {
            $this->error('The evaluation must close after it opens.', '/evaluations');
        }
        Database::statement('INSERT INTO evaluation_periods (semester_id, title, opens_at, closes_at, min_responses, status) VALUES (?, ?, ?, ?, ?, "open")',
            [(int) $data['semester_id'], $data['title'], $opens, $closes, max(1, (int) ($data['min_responses'] ?? 3))]);
        $id = Database::lastInsertId();
        $userIds = array_column(Database::select(
            'SELECT DISTINCT s.user_id FROM course_registrations cr JOIN students s ON s.id = cr.student_id WHERE cr.semester_id = ? AND cr.status <> "dropped"',
            [(int) $data['semester_id']]), 'user_id');
        Alerts::sendMany($userIds, 'exam_published', 'Course evaluation is open', 'Please evaluate your courses by ' . fdatetime($closes) . '. Your answers are anonymous.',
            '/portal/evaluations', 'info', 'chart');
        Audit::log('create', 'evaluations', 'evaluation_periods', (string) $id, 'Opened evaluation ' . $data['title']);
        $this->success('Evaluation opened; ' . count($userIds) . ' students were invited.', '/evaluations?period=' . $id);
    }

    public function togglePeriod(Request $request, string $id): never
    {
        $this->authorize('evaluations.manage');
        $this->verifyCsrf($request);
        Database::statement('UPDATE evaluation_periods SET status = IF(status = "open", "closed", "open") WHERE id = ?', [(int) $id]);
        $this->success('Evaluation updated.', '/evaluations?period=' . (int) $id);
    }

    /** Detailed results for one course in one period. */
    public function report(Request $request, string $periodId, string $offeringId): string
    {
        $period = Database::selectOne('SELECT * FROM evaluation_periods WHERE id = ?', [(int) $periodId]) ?? throw new HttpException(404);
        $offering = Database::selectOne(
            'SELECT o.*, c.code, c.title, CONCAT(u.first_name, " ", u.last_name) AS lecturer_name FROM course_offerings o JOIN courses c ON c.id = o.course_id
          LEFT JOIN staff st ON st.id = o.lecturer_id LEFT JOIN users u ON u.id = st.user_id WHERE o.id = ?', [(int) $offeringId]) ?? throw new HttpException(404);
        if (!Auth::can('evaluations.view') && (int) $offering['lecturer_id'] !== Lookup::myStaffId()) {
            throw new HttpException(403);
        }
        $responses = (int) Database::scalar('SELECT COUNT(*) FROM evaluation_responses WHERE period_id = ? AND offering_id = ?', [$period['id'], $offering['id']]);
        $hidden = $responses < (int) $period['min_responses'];
        return $this->view('evaluations.report', [
            'pageTitle' => 'Evaluation - ' . $offering['code'],
            'period'    => $period,
            'offering'  => $offering,
            'responses' => $responses,
            'enrolled'  => (int) Database::scalar('SELECT COUNT(*) FROM course_registrations WHERE offering_id = ? AND status <> "dropped"', [$offering['id']]),
            'hidden'    => $hidden,
            'questions' => $hidden ? [] : Database::select(
                'SELECT q.section, q.body, AVG(a.rating) AS average, COUNT(a.rating) AS n,
                        SUM(a.rating = 1) AS r1, SUM(a.rating = 2) AS r2, SUM(a.rating = 3) AS r3, SUM(a.rating = 4) AS r4, SUM(a.rating = 5) AS r5
                   FROM evaluation_questions q JOIN evaluation_answers a ON a.question_id = q.id JOIN evaluation_responses r ON r.id = a.response_id
                  WHERE r.period_id = ? AND r.offering_id = ? GROUP BY q.id ORDER BY q.sort_order', [$period['id'], $offering['id']]),
            'comments'  => $hidden ? [] : array_column(Database::select(
                'SELECT comments FROM evaluation_responses WHERE period_id = ? AND offering_id = ? AND comments IS NOT NULL ORDER BY RAND()', [$period['id'], $offering['id']]), 'comments'),
        ]);
    }

    /** A lecturer's own evaluation results. */
    public function mine(Request $request): string
    {
        $staffId = Lookup::myStaffId() ?? throw new HttpException(403);
        $rows = [];
        foreach (Database::select('SELECT id FROM evaluation_periods ORDER BY opens_at DESC LIMIT 6') as $p) {
            foreach ($this->summary((int) $p['id'], $staffId) as $row) {
                $rows[] = $row;
            }
        }
        return $this->view('evaluations.mine', ['pageTitle' => 'My Course Evaluations', 'rows' => $rows]);
    }

    /* ---------------- student side ---------------- */

    public function portal(Request $request): string
    {
        $studentId = $this->myStudentId();
        $open = Database::select('SELECT * FROM evaluation_periods WHERE status = "open" AND ? BETWEEN opens_at AND closes_at', [date('Y-m-d H:i:s')]);
        $courses = [];
        foreach ($open as $p) {
            foreach (Database::select(
                'SELECT o.id AS offering_id, c.code, c.title, CONCAT(u.first_name, " ", u.last_name) AS lecturer_name,
                        EXISTS (SELECT 1 FROM evaluation_submissions es WHERE es.period_id = ? AND es.offering_id = o.id AND es.student_id = ?) AS done
                   FROM course_registrations cr JOIN course_offerings o ON o.id = cr.offering_id JOIN courses c ON c.id = o.course_id
              LEFT JOIN staff st ON st.id = o.lecturer_id LEFT JOIN users u ON u.id = st.user_id
                  WHERE cr.student_id = ? AND cr.semester_id = ? AND cr.status <> "dropped" ORDER BY c.code',
                [$p['id'], $studentId, $studentId, $p['semester_id']]) as $c) {
                $courses[] = $c + ['period_id' => $p['id'], 'period_title' => $p['title'], 'closes_at' => $p['closes_at']];
            }
        }
        return $this->view('evaluations.portal', ['pageTitle' => 'Course Evaluations', 'courses' => $courses]);
    }

    public function form(Request $request, string $periodId, string $offeringId): string
    {
        [$period, $course] = $this->evaluable((int) $periodId, (int) $offeringId);
        return $this->view('evaluations.form', [
            'pageTitle' => 'Evaluate ' . $course['code'],
            'period'    => $period,
            'course'    => $course,
            'questions' => Database::select('SELECT * FROM evaluation_questions WHERE is_active = 1 ORDER BY sort_order'),
        ]);
    }

    public function submit(Request $request, string $periodId, string $offeringId): never
    {
        $this->verifyCsrf($request);
        [$period, $course, $studentId] = $this->evaluable((int) $periodId, (int) $offeringId);
        $questions = array_map('intval', array_column(Database::select('SELECT id FROM evaluation_questions WHERE is_active = 1'), 'id'));
        $ratings = (array) $request->input('rating', []);
        $answers = [];
        foreach ($questions as $q) {
            $v = (int) ($ratings[$q] ?? 0);
            if ($v < 1 || $v > 5) {
                $this->error('Please answer every statement.', '/portal/evaluations/' . $periodId . '/' . $offeringId);
            }
            $answers[$q] = $v;
        }
        Database::transaction(static function () use ($period, $course, $studentId, $answers, $request): void {
            Database::statement('INSERT INTO evaluation_submissions (period_id, offering_id, student_id, submitted_on) VALUES (?, ?, ?, ?)',
                [$period['id'], $course['offering_id'], $studentId, date('Y-m-d')]);
            Database::statement('INSERT INTO evaluation_responses (period_id, offering_id, comments) VALUES (?, ?, ?)',
                [$period['id'], $course['offering_id'], mb_substr(trim((string) $request->input('comments', '')), 0, 3000) ?: null]);
            $responseId = Database::lastInsertId();
            foreach ($answers as $q => $v) {
                Database::statement('INSERT INTO evaluation_answers (response_id, question_id, rating) VALUES (?, ?, ?)', [$responseId, $q, $v]);
            }
        });
        $this->success('Thank you! Your evaluation of ' . $course['code'] . ' has been recorded anonymously.', '/portal/evaluations');
    }

    /* ---------------- helpers ---------------- */

    /** Average rating, response count and rate for each course in a period (optionally one lecturer's). */
    private function summary(int $periodId, ?int $lecturerId): array
    {
        $sql = 'SELECT o.id AS offering_id, c.code, c.title, o.section, CONCAT(u.first_name, " ", u.last_name) AS lecturer_name, d.name AS department_name,
                       e.id AS period_id, e.title AS period_title, e.min_responses,
                       (SELECT COUNT(*) FROM course_registrations cr WHERE cr.offering_id = o.id AND cr.status <> "dropped") AS enrolled,
                       (SELECT COUNT(*) FROM evaluation_responses r WHERE r.period_id = e.id AND r.offering_id = o.id) AS responses,
                       (SELECT AVG(a.rating) FROM evaluation_answers a JOIN evaluation_responses r ON r.id = a.response_id WHERE r.period_id = e.id AND r.offering_id = o.id) AS average
                  FROM evaluation_periods e JOIN course_offerings o ON o.semester_id = e.semester_id JOIN courses c ON c.id = o.course_id
             LEFT JOIN departments d ON d.id = c.department_id LEFT JOIN staff st ON st.id = o.lecturer_id LEFT JOIN users u ON u.id = st.user_id
                 WHERE e.id = ? AND EXISTS (SELECT 1 FROM course_registrations cr WHERE cr.offering_id = o.id)';
        $params = [$periodId];
        if ($lecturerId !== null) {
            $sql .= ' AND o.lecturer_id = ?';
            $params[] = $lecturerId;
        }
        return Database::select($sql . ' ORDER BY d.name, c.code', $params);
    }

    /** @return array{0:array,1:array,2:int} the open period, the course, the student */
    private function evaluable(int $periodId, int $offeringId): array
    {
        $studentId = $this->myStudentId();
        $period = Database::selectOne('SELECT * FROM evaluation_periods WHERE id = ? AND status = "open" AND ? BETWEEN opens_at AND closes_at', [$periodId, date('Y-m-d H:i:s')])
            ?? throw new HttpException(404, 'This evaluation is not open.');
        $course = Database::selectOne(
            'SELECT o.id AS offering_id, c.code, c.title, CONCAT(u.first_name, " ", u.last_name) AS lecturer_name
               FROM course_registrations cr JOIN course_offerings o ON o.id = cr.offering_id JOIN courses c ON c.id = o.course_id
          LEFT JOIN staff st ON st.id = o.lecturer_id LEFT JOIN users u ON u.id = st.user_id
              WHERE cr.student_id = ? AND o.id = ? AND o.semester_id = ? AND cr.status <> "dropped"', [$studentId, $offeringId, $period['semester_id']])
            ?? throw new HttpException(404, 'You are not registered for this course.');
        if (Database::scalar('SELECT 1 FROM evaluation_submissions WHERE period_id = ? AND offering_id = ? AND student_id = ?', [$periodId, $offeringId, $studentId])) {
            $this->warning('You have already evaluated ' . $course['code'] . '. Thank you!', '/portal/evaluations');
        }
        return [$period, $course, $studentId];
    }

    private function myStudentId(): int
    {
        return (int) (Database::scalar('SELECT id FROM students WHERE user_id = ?', [Auth::id()]) ?: throw new HttpException(403, 'No student profile is linked to your account.'));
    }
}
