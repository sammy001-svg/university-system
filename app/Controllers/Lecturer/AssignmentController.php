<?php
declare(strict_types=1);

namespace App\Controllers\Lecturer;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Upload;
use App\Services\Alerts\Alerts;
use App\Services\Elearning\AssignmentService;
use App\Services\Elearning\CourseAccess;
use RuntimeException;

/** Lecturers set assignments, read and mark the submissions, and send the marks to the gradebook. */
final class AssignmentController extends Controller
{
    public function create(Request $request, string $id): string
    {
        $this->authorize('teaching.access');
        $offering = CourseAccess::teaching((int) $id);
        return $this->view('lecturer.assignments.form', [
            'pageTitle'  => 'New assignment - ' . $offering['code'],
            'offering'   => $offering,
            'assignment' => ['title' => '', 'instructions' => '', 'due_at' => date('Y-m-d 23:59:00', strtotime('+7 days')),
                             'max_score' => 100, 'submission_type' => 'file', 'allow_late' => 1, 'late_penalty' => 10],
        ]);
    }

    public function store(Request $request, string $id): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $offering = CourseAccess::teaching((int) $id);
        $data     = $this->data($request, '/teaching/' . $id . '/assignments/create');

        Database::statement(
            'INSERT INTO assignments (offering_id, title, instructions, attachment_path, attachment_name, due_at, max_score,
                                      submission_type, allow_late, late_penalty, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "published", ?)',
            [(int) $id, $data['title'], $data['instructions'], $data['attachment_path'], $data['attachment_name'], $data['due_at'],
             $data['max_score'], $data['submission_type'], $data['allow_late'], $data['late_penalty'], Auth::id()]
        );
        $assignmentId = Database::lastInsertId();
        Audit::log('create', 'elearning', 'assignments', (string) $assignmentId, "Set assignment {$data['title']} for {$offering['code']}");
        Alerts::sendMany($this->classUserIds((int) $id), 'assignment_published', 'New assignment: ' . $offering['code'],
            $data['title'] . ' - due ' . fdatetime($data['due_at']) . '.', '/portal/assignments/' . $assignmentId, 'info', 'file');
        $this->success('Assignment set; the class has been notified.', '/teaching/assignments/' . $assignmentId);
    }

    public function edit(Request $request, string $assignmentId): string
    {
        $this->authorize('teaching.access');
        $assignment = $this->assignment((int) $assignmentId);
        return $this->view('lecturer.assignments.form', [
            'pageTitle'  => 'Edit assignment',
            'offering'   => CourseAccess::teaching((int) $assignment['offering_id']),
            'assignment' => $assignment,
        ]);
    }

    public function update(Request $request, string $assignmentId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $assignment = $this->assignment((int) $assignmentId);
        $data = $this->data($request, '/teaching/assignments/' . $assignmentId . '/edit');
        if ($data['attachment_path'] !== null && $assignment['attachment_path']) {
            Upload::delete($assignment['attachment_path']);
        }
        Database::statement(
            'UPDATE assignments SET title = ?, instructions = ?, attachment_path = COALESCE(?, attachment_path), attachment_name = COALESCE(?, attachment_name),
                    due_at = ?, max_score = ?, submission_type = ?, allow_late = ?, late_penalty = ? WHERE id = ?',
            [$data['title'], $data['instructions'], $data['attachment_path'], $data['attachment_name'], $data['due_at'], $data['max_score'],
             $data['submission_type'], $data['allow_late'], $data['late_penalty'], $assignment['id']]
        );
        $this->success('Assignment updated.', '/teaching/assignments/' . $assignmentId);
    }

    public function destroy(Request $request, string $assignmentId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $assignment = $this->assignment((int) $assignmentId);
        if (Database::scalar('SELECT 1 FROM assignment_submissions WHERE assignment_id = ? LIMIT 1', [$assignment['id']])) {
            $this->error('Students have handed in work for this assignment, so it cannot be deleted.', '/teaching/assignments/' . $assignmentId);
        }
        Upload::delete($assignment['attachment_path']);
        Database::statement('DELETE FROM assignments WHERE id = ?', [$assignment['id']]);
        $this->success('Assignment deleted.', '/teaching/' . $assignment['offering_id'] . '/classroom#assignments');
    }

    /** The submissions, against the class list, with marking. */
    public function show(Request $request, string $assignmentId): string
    {
        $this->authorize('teaching.access');
        $assignment = $this->assignment((int) $assignmentId);
        $offering   = CourseAccess::teaching((int) $assignment['offering_id']);
        $rows = Database::select(
            'SELECT cr.student_id, s.admission_number, u.first_name, u.last_name, sub.*,
                    sub.id AS submission_id
               FROM course_registrations cr
               JOIN students s ON s.id = cr.student_id
               JOIN users u    ON u.id = s.user_id
          LEFT JOIN assignment_submissions sub ON sub.assignment_id = ? AND sub.student_id = cr.student_id
              WHERE cr.offering_id = ? AND cr.status <> "dropped"
              ORDER BY sub.id IS NULL, sub.graded_at IS NOT NULL, u.last_name, u.first_name',
            [$assignment['id'], $assignment['offering_id']]
        );

        if ($request->query('export') === 'csv') {
            Response::csv(array_map(static fn (array $r): array => [
                $r['admission_number'], $r['last_name'] . ', ' . $r['first_name'],
                $r['submission_id'] ? ($r['graded_at'] ? 'marked' : 'submitted') : 'not submitted',
                $r['submitted_at'] ?? '', $r['is_late'] ? 'yes' : '', $r['raw_score'] ?? '', $r['score'] ?? '', $assignment['max_score'],
            ], $rows), 'assignment-' . $assignment['id'] . '.csv', ['Student ID', 'Name', 'Status', 'Submitted', 'Late', 'Mark', 'Mark after penalty', 'Out of']);
        }

        return $this->view('lecturer.assignments.show', [
            'pageTitle'  => $assignment['title'],
            'assignment' => $assignment,
            'offering'   => $offering,
            'rows'       => $rows,
            'phase'      => AssignmentService::phase($assignment),
            'assessment' => $assignment['assessment_id'] ? Database::selectOne('SELECT * FROM assessments WHERE id = ?', [$assignment['assessment_id']]) : null,
        ]);
    }

    public function grade(Request $request, string $assignmentId): never
    {
        $this->authorize('teaching.access');
        $this->verifyCsrf($request);
        $assignment = $this->assignment((int) $assignmentId);
        $back = '/teaching/assignments/' . $assignmentId;
        if ($request->input('score') === null || $request->input('score') === '') {
            $this->error('Enter a mark.', $back . '#s' . (int) $request->input('submission_id'));
        }
        try {
            $score = AssignmentService::grade($assignment, (int) $request->input('submission_id'), (float) $request->input('score'),
                (string) $request->input('feedback', ''), (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), $back);
        }
        $this->success('Mark saved (' . rtrim(rtrim(number_format($score, 2), '0'), '.') . ').', $back . '#s' . (int) $request->input('submission_id'));
    }

    public function transfer(Request $request, string $assignmentId): never
    {
        $this->authorize(['teaching.access', 'results.create'], true);
        $this->verifyCsrf($request);
        $assignment = $this->assignment((int) $assignmentId);
        $counts = AssignmentService::transferToGradebook($assignment, max(0, min(100, (float) $request->input('weight', 10))),
            (int) Auth::id(), $request->input('missing_as_zero') === '1');
        $message = "{$counts['transferred']} mark(s) transferred to the gradebook";
        $message .= $counts['missed'] > 0 ? ", {$counts['missed']} recorded as not submitted" : '';
        $message .= $counts['pending'] > 0 ? ". {$counts['pending']} still to hand in or be marked." : '.';
        $this->success($message, '/teaching/assignments/' . $assignmentId);
    }

    private function assignment(int $id): array
    {
        $assignment = AssignmentService::find($id);
        if ($assignment === null) {
            throw new HttpException(404, 'Assignment not found.');
        }
        CourseAccess::teaching((int) $assignment['offering_id']);
        return $assignment;
    }

    private function classUserIds(int $offeringId): array
    {
        return array_column(Database::select(
            'SELECT s.user_id FROM course_registrations cr JOIN students s ON s.id = cr.student_id WHERE cr.offering_id = ? AND cr.status <> "dropped"',
            [$offeringId]
        ), 'user_id');
    }

    private function data(Request $request, string $back): array
    {
        $data = $this->validate($request, [
            'title'           => 'required|max:150',
            'due_at'          => 'required',
            'max_score'       => 'required|numeric',
            'submission_type' => 'required|in:' . implode(',', array_keys(AssignmentService::SUBMISSION_TYPES)),
            'late_penalty'    => 'nullable|integer',
            'instructions'    => 'nullable',
        ], ['due_at' => 'Deadline', 'max_score' => 'Marks']);
        $due = strtotime(str_replace('T', ' ', (string) $data['due_at']));
        if ($due === false) {
            $this->error('Enter a valid deadline.', $back);
        }
        $data['due_at']       = date('Y-m-d H:i:s', $due);
        $data['max_score']    = max(1, min(1000, (float) $data['max_score']));
        $data['late_penalty'] = max(0, min(100, (int) ($data['late_penalty'] ?? 0)));
        $data['allow_late']   = $request->input('allow_late') === '1' ? 1 : 0;
        $data['instructions'] = trim((string) ($data['instructions'] ?? '')) ?: null;
        $data['attachment_path'] = $data['attachment_name'] = null;
        $file = $request->file('attachment');
        if ($file !== null) {
            try {
                $stored = Upload::storePrivate($file, 'assignments', array_merge(Upload::DOCUMENTS, Upload::IMAGES, Upload::ARCHIVES), 25);
            } catch (RuntimeException $e) {
                $this->error($e->getMessage(), $back);
            }
            $data['attachment_path'] = $stored['path'];
            $data['attachment_name'] = $stored['name'];
        }
        return $data;
    }
}
