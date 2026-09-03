<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;

final class ClearanceController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('clearance.view');
        $students = Database::select(
            'SELECT s.id, s.admission_number, u.first_name, u.last_name, p.name AS program_name,
                    (SELECT COUNT(*) FROM clearances c WHERE c.student_id=s.id AND c.status="cleared") AS cleared_count,
                    (SELECT COUNT(*) FROM clearances c WHERE c.student_id=s.id) AS total_items
               FROM students s
               JOIN users u    ON u.id=s.user_id
               JOIN programs p ON p.id=s.program_id
              WHERE s.status IN ("active","alumni","graduated")
              ORDER BY u.last_name, u.first_name'
        );
        return $this->view('students.clearance.index', ['pageTitle' => 'Student Clearance', 'students' => $students]);
    }

    public function show(Request $request, string $student): string
    {
        $this->authorize('clearance.view');
        $profile = Database::selectOne(
            'SELECT s.*, u.first_name, u.last_name, u.email, p.name AS program_name
               FROM students s JOIN users u ON u.id=s.user_id JOIN programs p ON p.id=s.program_id
              WHERE s.id=?',
            [(int)$student]
        );
        if (!$profile) { throw new HttpException(404); }

        $items = Database::select('SELECT * FROM clearances WHERE student_id=? ORDER BY department', [(int)$student]);
        return $this->view('students.clearance.show', [
            'pageTitle' => 'Clearance – ' . $profile['first_name'] . ' ' . $profile['last_name'],
            'profile'   => $profile,
            'items'     => $items,
        ]);
    }

    public function update(Request $request, string $student): never
    {
        $this->authorize('clearance.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'department' => 'required|max:80',
            'status'     => 'required|in:pending,cleared,flagged',
            'remarks'    => 'nullable|max:255',
        ]);

        $existing = Database::scalar(
            'SELECT id FROM clearances WHERE student_id=? AND department=?',
            [(int)$student, $data['department']]
        );

        if ($existing) {
            Database::statement(
                'UPDATE clearances SET status=?, remarks=?, signed_by=?, signed_at=NOW() WHERE id=?',
                [$data['status'], $data['remarks'] ?? null, Auth::id(), (int)$existing]
            );
        } else {
            Database::statement(
                'INSERT INTO clearances (student_id, department, status, remarks, signed_by, signed_at) VALUES (?,?,?,?,?,NOW())',
                [(int)$student, $data['department'], $data['status'], $data['remarks'] ?? null, Auth::id()]
            );
        }
        $this->success('Clearance updated.', '/services/clearance/' . $student);
    }
}
