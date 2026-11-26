<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Models\User;

/**
 * Who teaches what. A lecturer's whole workspace - their classes, attendance,
 * marks, online lessons and examinations - hangs off this one link, so a class
 * with nobody against it is a class nobody can run.
 *
 * The registrar sets the assignments here for a whole semester at a time rather
 * than editing one class at a time, and sees each lecturer's load while doing it.
 */
final class TeachingAssignmentController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize(['offerings.edit', 'offerings.view']);

        $semesterId = $request->int('semester') ?: (int) Database::scalar(
            'SELECT id FROM semesters WHERE is_current = 1 ORDER BY id DESC LIMIT 1'
        );
        $faculty = $request->int('faculty');
        $search  = trim((string) $request->query('q', ''));
        $only    = (string) $request->query('show', '');

        $where  = ['o.semester_id = ?'];
        $params = [$semesterId];
        if ($faculty > 0) {
            $where[]  = 'f.id = ?';
            $params[] = $faculty;
        }
        if ($search !== '') {
            $where[]  = '(c.code LIKE ? OR c.title LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($only === 'unassigned') {
            $where[] = 'o.lecturer_id IS NULL';
        } elseif ($only === 'assigned') {
            $where[] = 'o.lecturer_id IS NOT NULL';
        }

        $offerings = Database::select(
            'SELECT o.id, o.section, o.capacity, o.enrolled_count, o.delivery_mode, o.lecturer_id,
                    c.code, c.title, c.credit_hours,
                    d.name AS department_name, f.name AS faculty_name,
                    CONCAT_WS(" ", lu.first_name, lu.last_name) AS lecturer_name
               FROM course_offerings o
               JOIN courses c          ON c.id = o.course_id
          LEFT JOIN departments d      ON d.id = c.department_id
          LEFT JOIN faculties f        ON f.id = d.faculty_id
          LEFT JOIN staff ls           ON ls.id = o.lecturer_id
          LEFT JOIN users lu           ON lu.id = ls.user_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY c.code, o.section',
            $params
        );

        return $this->view('academics.teaching-assignments', [
            'pageTitle' => 'Teaching Assignments',
            'semesters' => Database::select(
                'SELECT sem.id, CONCAT(ay.name, " ", sem.name) AS label
                   FROM semesters sem JOIN academic_years ay ON ay.id = sem.academic_year_id
                  ORDER BY ay.start_date DESC, sem.semester_number DESC'
            ),
            'faculties'  => Database::select('SELECT id, name FROM faculties ORDER BY name'),
            'lecturers'  => (new User())->teachingStaffOptions(),
            'offerings'  => $offerings,
            'workload'   => $this->workload($semesterId),
            'filters'    => ['semester' => $semesterId, 'faculty' => $faculty, 'q' => $search, 'show' => $only],
            'unassigned' => (int) Database::scalar(
                'SELECT COUNT(*) FROM course_offerings WHERE semester_id = ? AND lecturer_id IS NULL',
                [$semesterId]
            ),
            'idle'       => (int) Database::scalar(
                'SELECT COUNT(*) FROM staff st
                  WHERE st.staff_category = "academic" AND st.status = "active"
                    AND NOT EXISTS (SELECT 1 FROM course_offerings o WHERE o.lecturer_id = st.id AND o.semester_id = ?)',
                [$semesterId]
            ),
        ]);
    }

    /** Save the whole page's assignments at once. */
    public function save(Request $request): never
    {
        $this->authorize('offerings.edit');
        $this->verifyCsrf($request);

        $chosen = (array) $request->input('lecturer', []);
        if ($chosen === []) {
            $this->error('Nothing to save.', '/academics/teaching-assignments');
        }

        // Only academic staff may be given a class.
        $allowed = array_keys((new User())->teachingStaffOptions());

        $changed = 0;
        $skipped = 0;
        Database::beginTransaction();
        try {
            foreach ($chosen as $offeringId => $staffId) {
                $offeringId = (int) $offeringId;
                $staffId    = trim((string) $staffId) === '' ? null : (int) $staffId;
                if ($staffId !== null && !in_array($staffId, $allowed, true)) {
                    $skipped++;
                    continue;
                }
                $current = Database::scalar('SELECT lecturer_id FROM course_offerings WHERE id = ?', [$offeringId]);
                if ($current === null && $staffId === null) {
                    continue;
                }
                if ((int) $current === (int) $staffId) {
                    continue;
                }
                Database::statement('UPDATE course_offerings SET lecturer_id = ? WHERE id = ?', [$staffId, $offeringId]);
                Audit::log('assign', 'offerings', 'CourseOffering', (string) $offeringId,
                    $staffId === null ? 'Teaching assignment cleared' : 'Assigned to staff #' . $staffId,
                    ['lecturer_id' => $current], ['lecturer_id' => $staffId]);
                $changed++;
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        $back = '/academics/teaching-assignments?' . http_build_query(array_filter([
            'semester' => $request->int('semester'),
            'faculty'  => $request->int('faculty'),
            'q'        => $request->query('q'),
            'show'     => $request->query('show'),
        ]));

        if ($changed === 0) {
            $this->warning(
                $skipped > 0
                    ? 'No assignment changed: ' . $skipped . ' could not be set because the person chosen is not academic staff.'
                    : 'No assignment changed.',
                $back
            );
        }
        $this->success(
            $changed . ' class' . ($changed === 1 ? '' : 'es') . ' assigned.'
            . ($skipped > 0 ? ' ' . $skipped . ' skipped: not academic staff.' : ''),
            $back
        );
    }

    /**
     * What each lecturer is carrying this semester, so the load can be seen while
     * it is being set rather than discovered afterwards.
     */
    private function workload(int $semesterId): array
    {
        return Database::select(
            'SELECT st.id, CONCAT_WS(" ", u.first_name, u.last_name) AS name, st.staff_number,
                    COALESCE(f.name, "") AS faculty_name,
                    COUNT(o.id) AS classes,
                    COALESCE(SUM(c.credit_hours), 0) AS credits,
                    COALESCE(SUM(o.enrolled_count), 0) AS students,
                    u.password_hash = "!" AS never_signed_in
               FROM staff st
               JOIN users u       ON u.id = st.user_id
          LEFT JOIN faculties f   ON f.id = st.faculty_id
          LEFT JOIN course_offerings o ON o.lecturer_id = st.id AND o.semester_id = ?
          LEFT JOIN courses c     ON c.id = o.course_id
              WHERE st.staff_category = "academic" AND st.status = "active" AND u.deleted_at IS NULL
              GROUP BY st.id, u.first_name, u.last_name, st.staff_number, f.name, u.password_hash
              ORDER BY classes DESC, u.last_name',
            [$semesterId]
        );
    }
}
