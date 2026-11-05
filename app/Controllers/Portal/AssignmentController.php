<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Elearning\AssignmentService;
use App\Services\Elearning\CourseAccess;
use RuntimeException;

/** Students see their assignments, hand in work and read the marks and feedback. */
final class AssignmentController extends Controller
{
    use CurrentStudent;

    public function index(Request $request): string
    {
        $student = $this->student();
        $rows = Database::select(
            'SELECT a.*, c.code, c.title AS course_title,
                    sub.id AS submission_id, sub.submitted_at, sub.is_late, sub.score, sub.graded_at
               FROM assignments a
               JOIN course_offerings o ON o.id = a.offering_id
               JOIN courses c          ON c.id = o.course_id
          LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = ?
              WHERE a.status = "published" AND a.offering_id IN (' . CourseAccess::inList(CourseAccess::studiedOfferingIds((int) $student['id'])) . ')
              ORDER BY a.due_at',
            [$student['id']]
        );
        $groups = ['todo' => [], 'submitted' => [], 'marked' => [], 'missed' => []];
        foreach ($rows as $row) {
            $row['phase'] = AssignmentService::phase($row);
            $key = $row['graded_at'] !== null ? 'marked' : ($row['submission_id'] !== null ? 'submitted' : ($row['phase'] === 'closed' ? 'missed' : 'todo'));
            $groups[$key][] = $row;
        }
        $groups['marked'] = array_reverse($groups['marked']);
        return $this->view('portal.assignments.index', ['pageTitle' => 'Assignments', 'student' => $student, 'groups' => $groups]);
    }

    public function show(Request $request, string $id): string
    {
        [$assignment, $offering, $student] = $this->load((int) $id);
        return $this->view('portal.assignments.show', [
            'pageTitle'  => $assignment['title'],
            'assignment' => $assignment,
            'offering'   => $offering,
            'phase'      => AssignmentService::phase($assignment),
            'submission' => AssignmentService::submission((int) $assignment['id'], (int) $student['id']),
        ]);
    }

    public function submit(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        [$assignment, , $student] = $this->load((int) $id);
        try {
            AssignmentService::submit($assignment, (int) $student['id'], $request->file('file'), $request->raw('text_answer') !== null ? (string) $request->raw('text_answer') : null);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/portal/assignments/' . $id);
        }
        $this->success('Your work has been handed in' . (date('Y-m-d H:i:s') > $assignment['due_at'] ? ' (late)' : '') . '.', '/portal/assignments/' . $id);
    }

    /** @return array{0:array,1:array,2:array} */
    private function load(int $id): array
    {
        $student    = $this->student();
        $assignment = AssignmentService::find($id);
        if ($assignment === null || $assignment['status'] !== 'published') {
            throw new HttpException(404, 'Assignment not found.');
        }
        $offering = CourseAccess::studying((int) $assignment['offering_id'], (int) $student['id']);
        return [$assignment, $offering, $student];
    }
}
