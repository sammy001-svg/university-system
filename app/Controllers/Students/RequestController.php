<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Academic\RequestService;
use App\Support\PrivateFile;
use RuntimeException;

/** The registrar's queue of student requests, and the student's own requests in the portal. */
final class RequestController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('requests.view');
        $status = (string) $request->query('status', 'open');
        $where  = match ($status) { 'open' => 'r.status IN ("submitted","under_review")', 'all' => '1 = 1', default => 'r.status = ' . Database::connection()->quote($status) };
        $params = [];
        if ($request->filled('type')) {
            $where   .= ' AND r.request_type = ?';
            $params[] = $request->query('type');
        }
        return $this->view('students.requests', [
            'pageTitle' => 'Student Requests',
            'rows'      => Database::select(
                'SELECT r.*, s.admission_number, u.first_name, u.last_name, p.name AS program_name
                   FROM student_requests r JOIN students s ON s.id = r.student_id JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
                  WHERE ' . $where . ' ORDER BY r.status IN ("submitted","under_review") DESC, r.created_at ' . ($status === 'open' ? 'ASC' : 'DESC') . ' LIMIT 300',
                $params
            ),
            'counts'    => array_column(Database::select('SELECT status, COUNT(*) AS n FROM student_requests GROUP BY status'), 'n', 'status'),
            'status'    => $status,
        ]);
    }

    public function show(Request $request, string $id): string
    {
        $r = $this->find((int) $id);
        return $this->view('students.request-show', [
            'pageTitle' => $r['reference'],
            'r'         => $r,
            'history'   => Database::select('SELECT reference, request_type, status, created_at FROM student_requests WHERE student_id = ? AND id <> ? ORDER BY created_at DESC', [$r['student_id'], $r['id']]),
            'canReview' => Auth::can('requests.review'),
            'isOwner'   => (int) $r['user_id'] === (int) Auth::id(),
        ]);
    }

    public function decide(Request $request, string $id): never
    {
        $this->authorize('requests.review');
        $this->verifyCsrf($request);
        try {
            $message = RequestService::decide((int) $id, (string) $request->input('decision'), (string) $request->input('note', ''), (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/requests/' . $id);
        }
        $this->success($message, '/requests/' . $id);
    }

    public function attachment(Request $request, string $id): never
    {
        $r = $this->find((int) $id);
        PrivateFile::send($r['attachment_path'], $r['attachment_name']);
    }

    /* ---------------- student side ---------------- */

    public function mine(Request $request): string
    {
        $student = $this->myStudent();
        return $this->view('portal.requests', [
            'pageTitle' => 'My Requests',
            'student'   => $student,
            'rows'      => Database::select('SELECT * FROM student_requests WHERE student_id = ? ORDER BY created_at DESC', [$student['id']]),
            'courses'   => array_column(Database::select(
                'SELECT o.id, CONCAT(c.code, " ", c.title, " (", ay.name, " ", sem.name, ")") AS label FROM course_registrations cr
                   JOIN course_offerings o ON o.id = cr.offering_id JOIN courses c ON c.id = o.course_id JOIN semesters sem ON sem.id = o.semester_id
                   JOIN academic_years ay ON ay.id = sem.academic_year_id WHERE cr.student_id = ? ORDER BY ay.start_date DESC, c.code', [$student['id']]), 'label', 'id'),
            'programs'  => array_column(Database::select('SELECT id, name FROM programs WHERE status = "active" AND id <> ? ORDER BY name', [$student['program_id']]), 'name', 'id'),
        ]);
    }

    public function store(Request $request): never
    {
        $this->verifyCsrf($request);
        $student = $this->myStudent();
        try {
            $r = RequestService::submit((int) $student['id'], (string) $request->input('request_type'), (string) $request->input('details', ''),
                $request->input('effective_date') ?: null, $request->input('offering_id') ? (int) $request->input('offering_id') : null,
                $request->input('target_program_id') ? (int) $request->input('target_program_id') : null, $request->file('attachment'));
        } catch (RuntimeException $e) {
            \App\Core\Session::keepOldInput($request->all());
            $this->error($e->getMessage(), '/portal/requests');
        }
        $this->success('Request ' . $r['reference'] . ' submitted. You will be notified of the decision.', '/portal/requests');
    }

    public function cancel(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        $student = $this->myStudent();
        Database::statement('UPDATE student_requests SET status = "cancelled", updated_at = ? WHERE id = ? AND student_id = ? AND status = "submitted"',
            [date('Y-m-d H:i:s'), (int) $id, $student['id']]);
        $this->success('Request withdrawn.', '/portal/requests');
    }

    private function find(int $id): array
    {
        $r = Database::selectOne(
            'SELECT r.*, s.admission_number, s.user_id, s.status AS student_status, s.year_of_study, u.first_name, u.last_name, u.email, u.phone,
                    p.name AS program_name, tp.name AS target_program_name, CONCAT(c.code, " ", c.title) AS course_label,
                    CONCAT(ru.first_name, " ", ru.last_name) AS reviewer_name
               FROM student_requests r JOIN students s ON s.id = r.student_id JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
          LEFT JOIN programs tp ON tp.id = r.target_program_id
          LEFT JOIN course_offerings o ON o.id = r.offering_id LEFT JOIN courses c ON c.id = o.course_id
          LEFT JOIN users ru ON ru.id = r.reviewed_by
              WHERE r.id = ?',
            [$id]
        ) ?? throw new HttpException(404, 'Request not found.');
        if (!Auth::can('requests.view') && (int) $r['user_id'] !== (int) Auth::id()) {
            throw new HttpException(403);
        }
        return $r;
    }

    private function myStudent(): array
    {
        return Database::selectOne('SELECT * FROM students WHERE user_id = ?', [Auth::id()]) ?? throw new HttpException(403, 'No student profile is linked to your account.');
    }
}
