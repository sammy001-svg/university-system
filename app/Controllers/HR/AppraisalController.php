<?php
declare(strict_types=1);

namespace App\Controllers\HR;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Notify;
use App\Core\Request;
use App\Support\Lookup;

/**
 * Annual staff appraisal: HR opens a cycle (every active staff member gets an appraisal,
 * with their college dean as default appraiser), the staff member completes a self-
 * assessment, the appraiser rates and comments, and the staff member acknowledges.
 */
final class AppraisalController extends Controller
{
    public const RATINGS = [1 => 'Unsatisfactory', 2 => 'Needs improvement', 3 => 'Meets expectations', 4 => 'Exceeds expectations', 5 => 'Outstanding'];
    public const AREAS   = ['rating_quality' => 'Quality of work', 'rating_teaching' => 'Teaching / core duties', 'rating_research' => 'Research & scholarship',
                            'rating_service' => 'Service & teamwork', 'rating_conduct' => 'Professional conduct'];

    /** HR overview: cycles and progress. */
    public function index(Request $request): string
    {
        $this->authorize('appraisals.view');
        $cycles = Database::select(
            'SELECT c.*, COUNT(a.id) AS total, SUM(a.status = "pending") AS pending, SUM(a.status = "self_submitted") AS awaiting_review,
                    SUM(a.status IN ("reviewed","acknowledged")) AS done, AVG(a.overall_rating) AS average
               FROM appraisal_cycles c LEFT JOIN appraisals a ON a.cycle_id = c.id GROUP BY c.id ORDER BY c.period_end DESC'
        );
        $cycleId = (int) $request->query('cycle', $cycles[0]['id'] ?? 0);
        return $this->view('hr.appraisals', [
            'pageTitle' => 'Staff Appraisal',
            'cycles'    => $cycles,
            'cycleId'   => $cycleId,
            'rows'      => $cycleId ? $this->rows('a.cycle_id = ?', [$cycleId]) : [],
            'staff'     => Lookup::staff(),
            'canManage' => Auth::can('appraisals.manage'),
        ]);
    }

    public function storeCycle(Request $request): never
    {
        $this->authorize('appraisals.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['title' => 'required|max:150', 'period_start' => 'required|date', 'period_end' => 'required|date', 'due_date' => 'nullable|date']);
        Database::statement('INSERT INTO appraisal_cycles (title, period_start, period_end, due_date) VALUES (?, ?, ?, ?)',
            [$data['title'], $data['period_start'], $data['period_end'], ($data['due_date'] ?? '') ?: null]);
        $cycleId = Database::lastInsertId();

        // Every active staff member, appraised by their college's dean (HR can change it).
        $staff = Database::select(
            'SELECT s.id, s.user_id, f.dean_id FROM staff s LEFT JOIN departments d ON d.id = s.department_id
               LEFT JOIN faculties f ON f.id = COALESCE(d.faculty_id, s.faculty_id)
               JOIN users u ON u.id = s.user_id WHERE s.status = "active" AND u.deleted_at IS NULL'
        );
        foreach ($staff as $s) {
            $reviewer = $s['dean_id'] !== null && (int) $s['dean_id'] !== (int) $s['id'] ? (int) $s['dean_id'] : null;
            Database::statement('INSERT IGNORE INTO appraisals (cycle_id, staff_id, reviewer_id) VALUES (?, ?, ?)', [$cycleId, $s['id'], $reviewer]);
        }
        Notify::sendMany(array_column($staff, 'user_id'), 'Staff appraisal: ' . $data['title'],
            'Please complete your self-assessment' . (($data['due_date'] ?? '') ? ' by ' . fdate($data['due_date']) : '') . '.', '/appraisals/mine');
        Audit::log('appraisal_cycle', 'hr', 'appraisal_cycles', (string) $cycleId, 'Opened appraisal cycle ' . $data['title'] . ' for ' . count($staff) . ' staff');
        $this->success('Cycle opened for ' . count($staff) . ' staff members; everyone has been notified.', '/hr/appraisals?cycle=' . $cycleId);
    }

    /** My appraisal(s), as the person being appraised. */
    public function mine(Request $request): string
    {
        $staffId = Lookup::myStaffId() ?? throw new HttpException(403, 'No staff profile is linked to your account.');
        return $this->view('hr.appraisal-mine', [
            'pageTitle'  => 'My Appraisal',
            'appraisals' => $this->rows('a.staff_id = ?', [$staffId]),
            'reviews'    => $this->rows('a.reviewer_id = ? AND c.status = "open"', [$staffId]),
        ]);
    }

    public function show(Request $request, string $id): string
    {
        $a = $this->appraisal((int) $id);
        $me = Lookup::myStaffId();
        return $this->view('hr.appraisal-show', [
            'pageTitle'  => 'Appraisal - ' . $a['staff_name'],
            'a'          => $a,
            'isSelf'     => $me !== null && (int) $a['staff_id'] === $me,
            'isReviewer' => $me !== null && (int) $a['reviewer_id'] === $me,
            'research'   => Database::select('SELECT title, output_type, year, status FROM research_outputs WHERE staff_id = ? AND year BETWEEN ? AND ? ORDER BY year DESC',
                [$a['staff_id'], substr($a['period_start'], 0, 4), substr($a['period_end'], 0, 4)]),
        ]);
    }

    /** The staff member's self-assessment (and later their acknowledgement). */
    public function selfAssess(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        $a = $this->appraisal((int) $id);
        if ((int) $a['staff_id'] !== Lookup::myStaffId()) {
            throw new HttpException(403);
        }
        $back = '/appraisals/' . $id;
        if ($request->input('action') === 'acknowledge') {
            if ($a['status'] !== 'reviewed') {
                $this->error('The appraisal has not been reviewed yet.', $back);
            }
            Database::statement('UPDATE appraisals SET staff_comments = ?, status = "acknowledged", acknowledged_at = ? WHERE id = ?',
                [trim((string) $request->input('staff_comments', '')) ?: null, date('Y-m-d H:i:s'), $a['id']]);
            $this->success('Thank you. Your appraisal is complete.', $back);
        }
        if (!in_array($a['status'], ['pending', 'self_submitted'], true) || $a['cycle_status'] !== 'open') {
            $this->error('This appraisal can no longer be changed.', $back);
        }
        $submit = $request->input('action') === 'submit';
        if ($submit && trim((string) $request->input('achievements', '')) === '') {
            $this->error('Describe your main achievements before submitting.', $back);
        }
        Database::statement(
            'UPDATE appraisals SET achievements = ?, challenges = ?, goals_next = ?, training_needs = ?, self_rating = ?,
                    status = ?, self_submitted_at = IF(? = "self_submitted", ?, self_submitted_at) WHERE id = ?',
            [trim((string) $request->input('achievements', '')) ?: null, trim((string) $request->input('challenges', '')) ?: null,
             trim((string) $request->input('goals_next', '')) ?: null, trim((string) $request->input('training_needs', '')) ?: null,
             $this->rating($request->input('self_rating')), $submit ? 'self_submitted' : $a['status'],
             $submit ? 'self_submitted' : $a['status'], date('Y-m-d H:i:s'), $a['id']]
        );
        if ($submit && $a['reviewer_user_id']) {
            Notify::send((int) $a['reviewer_user_id'], 'Appraisal to review', $a['staff_name'] . ' has submitted a self-assessment.', '/appraisals/' . $a['id']);
        }
        $this->success($submit ? 'Self-assessment submitted to your appraiser.' : 'Draft saved.', $back);
    }

    /** The appraiser's ratings and comments. */
    public function review(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        $a = $this->appraisal((int) $id);
        $back = '/appraisals/' . $id;
        if ((int) $a['reviewer_id'] !== Lookup::myStaffId() && !Auth::can('appraisals.manage')) {
            throw new HttpException(403);
        }
        if ($a['status'] !== 'self_submitted' && $a['status'] !== 'reviewed') {
            $this->error('The staff member has not submitted a self-assessment yet.', $back);
        }
        $ratings = [];
        foreach (array_keys(self::AREAS) as $area) {
            $ratings[$area] = $this->rating($request->input($area));
        }
        $given = array_filter($ratings);
        if (count($given) < 3) {
            $this->error('Rate at least three areas.', $back);
        }
        Database::statement(
            'UPDATE appraisals SET rating_quality = ?, rating_teaching = ?, rating_research = ?, rating_service = ?, rating_conduct = ?,
                    overall_rating = ?, reviewer_comments = ?, status = "reviewed", reviewed_at = ? WHERE id = ?',
            [...array_values($ratings), round(array_sum($given) / count($given), 2), trim((string) $request->input('reviewer_comments', '')) ?: null,
             date('Y-m-d H:i:s'), $a['id']]
        );
        Notify::send((int) $a['staff_user_id'], 'Your appraisal has been reviewed', 'Read your appraiser\'s comments and acknowledge the appraisal.', '/appraisals/' . $a['id']);
        Audit::log('appraisal_review', 'hr', 'appraisals', (string) $a['id'], 'Appraisal reviewed');
        $this->success('Review saved; the staff member has been asked to acknowledge it.', $back);
    }

    public function assignReviewer(Request $request, string $id): never
    {
        $this->authorize('appraisals.manage');
        $this->verifyCsrf($request);
        $a = $this->appraisal((int) $id);
        $reviewer = $request->input('reviewer_id') ? (int) $request->input('reviewer_id') : null;
        if ($reviewer === (int) $a['staff_id']) {
            $this->error('A staff member cannot appraise themself.', '/hr/appraisals?cycle=' . $a['cycle_id']);
        }
        Database::statement('UPDATE appraisals SET reviewer_id = ? WHERE id = ?', [$reviewer, $a['id']]);
        $this->success('Appraiser updated.', '/hr/appraisals?cycle=' . $a['cycle_id']);
    }

    public function closeCycle(Request $request, string $id): never
    {
        $this->authorize('appraisals.manage');
        $this->verifyCsrf($request);
        Database::statement('UPDATE appraisal_cycles SET status = IF(status = "open", "closed", "open") WHERE id = ?', [(int) $id]);
        $this->success('Cycle updated.', '/hr/appraisals?cycle=' . (int) $id);
    }

    /* ---------------- helpers ---------------- */

    private function rows(string $where, array $params): array
    {
        return Database::select(
            'SELECT a.*, c.title AS cycle_title, c.status AS cycle_status, c.due_date, c.period_start, c.period_end,
                    CONCAT(u.first_name, " ", u.last_name) AS staff_name, s.designation, s.staff_number,
                    CONCAT(ru.first_name, " ", ru.last_name) AS reviewer_name
               FROM appraisals a JOIN appraisal_cycles c ON c.id = a.cycle_id
               JOIN staff s ON s.id = a.staff_id JOIN users u ON u.id = s.user_id
          LEFT JOIN staff rs ON rs.id = a.reviewer_id LEFT JOIN users ru ON ru.id = rs.user_id
              WHERE ' . $where . ' ORDER BY c.period_end DESC, u.last_name, u.first_name',
            $params
        );
    }

    private function appraisal(int $id): array
    {
        $a = Database::selectOne(
            'SELECT a.*, c.title AS cycle_title, c.status AS cycle_status, c.due_date, c.period_start, c.period_end,
                    CONCAT(u.first_name, " ", u.last_name) AS staff_name, s.designation, s.staff_number, s.user_id AS staff_user_id,
                    CONCAT(ru.first_name, " ", ru.last_name) AS reviewer_name, rs.user_id AS reviewer_user_id
               FROM appraisals a JOIN appraisal_cycles c ON c.id = a.cycle_id
               JOIN staff s ON s.id = a.staff_id JOIN users u ON u.id = s.user_id
          LEFT JOIN staff rs ON rs.id = a.reviewer_id LEFT JOIN users ru ON ru.id = rs.user_id
              WHERE a.id = ?',
            [$id]
        ) ?? throw new HttpException(404, 'Appraisal not found.');
        $me = Lookup::myStaffId();
        if (!Auth::can('appraisals.view') && (int) $a['staff_id'] !== $me && (int) $a['reviewer_id'] !== $me) {
            throw new HttpException(403);
        }
        return $a;
    }

    private function rating(mixed $value): ?int
    {
        $v = (int) $value;
        return $v >= 1 && $v <= 5 ? $v : null;
    }
}
