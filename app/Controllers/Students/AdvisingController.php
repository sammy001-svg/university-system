<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Setting;
use App\Services\Alerts\Alerts;
use App\Support\Lookup;

/**
 * Academic advising (advisor assignment, advisees, advising notes) and academic standing:
 * probation (CGPA below the minimum), academic warning (last semester GPA below it while
 * the CGPA is still fine) and the Dean's List (last semester GPA 3.50+ on 12+ credits).
 */
final class AdvisingController extends Controller
{
    public const DEANS_LIST_GPA = 3.50;
    public const DEANS_LIST_CREDITS = 12;

    /** Advisors, advisee numbers and assignment (registrar). */
    public function index(Request $request): string
    {
        $this->authorize('advising.view');
        return $this->view('advising.index', [
            'pageTitle' => 'Academic Advising',
            'advisors'  => Database::select(
                'SELECT st.id, CONCAT(u.first_name, " ", u.last_name) AS name, st.designation, COUNT(s.id) AS advisees,
                        (SELECT MAX(n.meeting_date) FROM advising_notes n WHERE n.staff_id = st.id) AS last_meeting
                   FROM students s JOIN staff st ON st.id = s.advisor_id JOIN users u ON u.id = st.user_id
                  WHERE s.status = "active" GROUP BY st.id ORDER BY name'
            ),
            'unassigned'=> Database::select(
                'SELECT p.id, p.name, COUNT(*) AS n FROM students s JOIN programs p ON p.id = s.program_id
                  WHERE s.status = "active" AND s.advisor_id IS NULL GROUP BY p.id ORDER BY n DESC'
            ),
            'programs'  => Lookup::programs(),
            'staff'     => Lookup::staff(),
            'canManage' => Auth::can('advising.manage'),
        ]);
    }

    public function assign(Request $request): never
    {
        $this->authorize('advising.manage');
        $this->verifyCsrf($request);
        $advisor = (int) $request->input('advisor_id');
        if (!Database::scalar('SELECT 1 FROM staff WHERE id = ?', [$advisor])) {
            $this->error('Choose the advisor.', '/advising');
        }
        $sql = 'UPDATE students SET advisor_id = ? WHERE status = "active" AND program_id = ?';
        $params = [$advisor, (int) $request->input('program_id')];
        if ($request->filled('year_of_study')) {
            $sql .= ' AND year_of_study = ?';
            $params[] = (int) $request->input('year_of_study');
        }
        if ($request->input('only_unassigned') === '1') {
            $sql .= ' AND advisor_id IS NULL';
        }
        $n = Database::statement($sql, $params);
        Audit::log('advisor_assign', 'advising', 'students', null, "Assigned advisor #{$advisor} to {$n} student(s)");
        $userId = Database::scalar('SELECT user_id FROM staff WHERE id = ?', [$advisor]);
        if ($n > 0 && $userId) {
            \App\Core\Notify::send((int) $userId, 'New advisees', "You are now the academic advisor of {$n} more student(s).", '/advising/mine');
        }
        $this->success("{$n} student(s) assigned.", '/advising');
    }

    public function assignOne(Request $request, string $studentId): never
    {
        $this->authorize('advising.manage');
        $this->verifyCsrf($request);
        Database::statement('UPDATE students SET advisor_id = ? WHERE id = ?', [$request->input('advisor_id') ?: null, (int) $studentId]);
        $this->success('Advisor updated.', '/advising/students/' . (int) $studentId);
    }

    /** The signed-in staff member's advisees. */
    public function mine(Request $request): string
    {
        $staffId = Lookup::myStaffId() ?? throw new HttpException(403, 'No staff profile is linked to your account.');
        return $this->view('advising.mine', [
            'pageTitle' => 'My Advisees',
            'rows'      => $this->withStanding('s.advisor_id = ? AND s.status = "active"', [$staffId]),
            'minCgpa'   => $this->minCgpa(),
        ]);
    }

    /** A student's advising record: standing, notes and the advisor. */
    public function student(Request $request, string $id): string
    {
        $row = $this->withStanding('s.id = ?', [(int) $id])[0] ?? throw new HttpException(404, 'Student not found.');
        $this->mayAdvise($row);
        return $this->view('advising.student', [
            'pageTitle' => 'Advising - ' . $row['first_name'] . ' ' . $row['last_name'],
            's'         => $row,
            'notes'     => Database::select(
                'SELECT n.*, CONCAT(u.first_name, " ", u.last_name) AS by_name FROM advising_notes n
              LEFT JOIN staff st ON st.id = n.staff_id LEFT JOIN users u ON u.id = st.user_id WHERE n.student_id = ? ORDER BY n.meeting_date DESC, n.id DESC',
                [(int) $id]
            ),
            'terms'     => Database::select(
                'SELECT CONCAT(ay.name, " ", sem.name) AS term, SUM(r.quality_points) / NULLIF(SUM(r.credit_hours), 0) AS gpa, SUM(r.credit_hours) AS credits,
                        SUM(r.outcome <> "pass") AS failed
                   FROM course_results r JOIN semesters sem ON sem.id = r.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
                  WHERE r.student_id = ? AND r.is_published = 1 GROUP BY r.semester_id ORDER BY ay.start_date, sem.semester_number',
                [(int) $id]
            ),
            'staff'     => Lookup::staff(),
            'canManage' => Auth::can('advising.manage'),
            'minCgpa'   => $this->minCgpa(),
        ]);
    }

    public function addNote(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        $row = $this->withStanding('s.id = ?', [(int) $id])[0] ?? throw new HttpException(404);
        $this->mayAdvise($row);
        $data = $this->validate($request, ['meeting_date' => 'required|date', 'note' => 'required', 'follow_up_on' => 'nullable|date']);
        $visible = $request->input('visible_to_student') === '1' ? 1 : 0;
        Database::statement(
            'INSERT INTO advising_notes (student_id, staff_id, meeting_date, note, follow_up_on, visible_to_student, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, Lookup::myStaffId(), $data['meeting_date'], trim((string) $data['note']), ($data['follow_up_on'] ?? '') ?: null, $visible, date('Y-m-d H:i:s')]
        );
        if ($visible) {
            Alerts::send((int) $row['user_id'], 'document_issued', 'Advising note from your advisor', 'Your academic advisor added a note after your meeting.', '/portal/advising', 'info', 'users');
        }
        $this->success('Note saved.', '/advising/students/' . (int) $id);
    }

    /** Probation, academic warning and the Dean's List. */
    public function standing(Request $request): string
    {
        $this->authorize('advising.view');
        $rows = $this->withStanding('s.status = "active"', []);
        $min  = $this->minCgpa();
        $groups = ['probation' => [], 'warning' => [], 'deans_list' => []];
        foreach ($rows as $r) {
            if ($r['cgpa'] === null) {
                continue;
            }
            if ((float) $r['cgpa'] < $min) {
                $groups['probation'][] = $r;
            } elseif ($r['last_gpa'] !== null && (float) $r['last_gpa'] < $min) {
                $groups['warning'][] = $r;
            }
            if ($r['last_gpa'] !== null && (float) $r['last_gpa'] >= self::DEANS_LIST_GPA && (int) $r['last_credits'] >= self::DEANS_LIST_CREDITS) {
                $groups['deans_list'][] = $r;
            }
        }
        $list = (string) $request->query('list', 'probation');
        if ($request->query('export') === 'csv' && isset($groups[$list])) {
            Response::csv(array_map(static fn (array $r): array => [$r['admission_number'], $r['last_name'] . ', ' . $r['first_name'], $r['program_name'],
                number_format((float) $r['cgpa'], 2), $r['last_gpa'] !== null ? number_format((float) $r['last_gpa'], 2) : '', $r['last_term'] ?? '', $r['advisor_name'] ?? ''],
                $groups[$list]), $list . '.csv', ['Student ID', 'Name', 'Programme', 'CGPA', 'Last semester GPA', 'Semester', 'Advisor']);
        }
        return $this->view('advising.standing', ['pageTitle' => 'Academic Standing', 'groups' => $groups, 'list' => $list, 'minCgpa' => $min]);
    }

    /** The student's view: their advisor and the notes shared with them. */
    public function myAdvising(Request $request): string
    {
        $studentId = (int) (Database::scalar('SELECT id FROM students WHERE user_id = ?', [Auth::id()]) ?: throw new HttpException(403));
        $row = $this->withStanding('s.id = ?', [$studentId])[0];
        return $this->view('advising.portal', [
            'pageTitle' => 'My Advisor',
            's'         => $row,
            'advisor'   => $row['advisor_id'] ? Database::selectOne(
                'SELECT CONCAT_WS(" ", u.title, u.first_name, u.last_name) AS name, u.email, u.phone, st.designation
                   FROM staff st JOIN users u ON u.id = st.user_id WHERE st.id = ?', [$row['advisor_id']]) : null,
            'notes'     => Database::select(
                'SELECT n.meeting_date, n.note, n.follow_up_on, CONCAT(u.first_name, " ", u.last_name) AS by_name FROM advising_notes n
              LEFT JOIN staff st ON st.id = n.staff_id LEFT JOIN users u ON u.id = st.user_id
                  WHERE n.student_id = ? AND n.visible_to_student = 1 ORDER BY n.meeting_date DESC', [$studentId]),
            'minCgpa'   => $this->minCgpa(),
        ]);
    }

    /* ---------------- helpers ---------------- */

    /** Students with CGPA, their latest published semester GPA, credits and advisor. */
    private function withStanding(string $where, array $params): array
    {
        return Database::select(
            'SELECT s.id, s.user_id, s.admission_number, s.year_of_study, s.current_semester, s.advisor_id, s.status,
                    u.first_name, u.last_name, u.email, u.phone, p.name AS program_name,
                    CONCAT(au.first_name, " ", au.last_name) AS advisor_name,
                    (SELECT SUM(r.quality_points) / NULLIF(SUM(r.credit_hours), 0) FROM course_results r WHERE r.student_id = s.id AND r.is_published = 1) AS cgpa,
                    lt.gpa AS last_gpa, lt.credits AS last_credits, lt.term AS last_term,
                    (SELECT MAX(n.meeting_date) FROM advising_notes n WHERE n.student_id = s.id) AS last_meeting,
                    (SELECT se.status FROM semester_enrollments se JOIN semesters cs ON cs.id = se.semester_id AND cs.is_current = 1 WHERE se.student_id = s.id LIMIT 1) AS enrolment
               FROM students s JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
          LEFT JOIN staff ast ON ast.id = s.advisor_id LEFT JOIN users au ON au.id = ast.user_id
          LEFT JOIN (
                SELECT r.student_id, CONCAT(ay.start_date, "#", sem.semester_number) AS term_key,
                       SUM(r.quality_points) / NULLIF(SUM(r.credit_hours), 0) AS gpa, SUM(r.credit_hours) AS credits,
                       CONCAT(ay.name, " ", sem.name) AS term
                  FROM course_results r JOIN semesters sem ON sem.id = r.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
                 WHERE r.is_published = 1 GROUP BY r.student_id, r.semester_id, ay.start_date, sem.semester_number, ay.name, sem.name
          ) lt ON lt.student_id = s.id AND lt.term_key = (
                SELECT MAX(CONCAT(ay2.start_date, "#", sem2.semester_number)) FROM course_results r2
                  JOIN semesters sem2 ON sem2.id = r2.semester_id JOIN academic_years ay2 ON ay2.id = sem2.academic_year_id
                 WHERE r2.student_id = s.id AND r2.is_published = 1)
              WHERE u.deleted_at IS NULL AND ' . $where . '
              ORDER BY u.last_name, u.first_name',
            $params
        );
    }

    private function mayAdvise(array $student): void
    {
        if (!Auth::can('advising.view') && (int) $student['advisor_id'] !== Lookup::myStaffId()) {
            throw new HttpException(403, 'You are not this student\'s advisor.');
        }
    }

    private function minCgpa(): float
    {
        return (float) Setting::get('graduation_min_cgpa', 2.0);
    }
}
