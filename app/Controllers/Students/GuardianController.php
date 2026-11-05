<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Academic\DocumentService;
use App\Services\DataImport\Importer;

/**
 * A student's parents, guardians and sponsors, and their read-only portal access.
 * One parent account can be linked to several students (brothers and sisters).
 */
final class GuardianController extends Controller
{
    public function index(Request $request, string $id): string
    {
        $this->authorize('students.view');
        $student = DocumentService::student((int) $id) ?? throw new HttpException(404, 'Student not found.');
        return $this->view('records.guardians', [
            'pageTitle' => 'Parents and sponsors',
            'student'   => $student,
            'guardians' => Database::select(
                'SELECT g.*, u.username, u.status AS account_status, u.last_login_at, u.password_hash = ? AS awaiting,
                        (SELECT COUNT(*) FROM guardians g2 WHERE g2.user_id = g.user_id) AS linked_students
                   FROM guardians g LEFT JOIN users u ON u.id = g.user_id WHERE g.student_id = ? ORDER BY g.is_primary DESC, g.full_name',
                [Importer::NO_PASSWORD, (int) $id]
            ),
        ]);
    }

    public function store(Request $request, string $id): never
    {
        $this->authorize('students.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'full_name'    => 'required|max:150',
            'relationship' => 'required|max:60',
            'phone'        => 'nullable|max:30',
            'email'        => 'nullable|email|max:150',
            'occupation'   => 'nullable|max:120',
        ]);
        $primary = $request->input('is_primary') === '1' ? 1 : 0;
        if ($primary) {
            Database::statement('UPDATE guardians SET is_primary = 0 WHERE student_id = ?', [(int) $id]);
        }
        Database::statement(
            'INSERT INTO guardians (student_id, full_name, relationship, phone, email, occupation, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, $data['full_name'], $data['relationship'], ($data['phone'] ?? '') ?: null, ($data['email'] ?? '') ?: null,
             ($data['occupation'] ?? '') ?: null, $primary]
        );
        $this->success('Added ' . $data['full_name'] . '.', '/students/' . $id . '/guardians');
    }

    /** Give the guardian a parent login (or link an existing parent account with the same email or phone). */
    public function grantAccess(Request $request, string $guardianId): never
    {
        $this->authorize(['students.edit', 'users.create'], true);
        $this->verifyCsrf($request);
        $g = Database::selectOne('SELECT * FROM guardians WHERE id = ?', [(int) $guardianId]) ?? throw new HttpException(404);
        $back = '/students/' . $g['student_id'] . '/guardians';
        if ($g['user_id']) {
            $this->warning('This guardian already has portal access.', $back);
        }
        if (!$g['phone'] && !$g['email']) {
            $this->error('Add a phone number or email first; the parent signs in with it.', $back);
        }

        $existing = Database::selectOne(
            'SELECT id, username FROM users WHERE user_type = "parent" AND deleted_at IS NULL AND ((email IS NOT NULL AND email = ?) OR (phone IS NOT NULL AND phone = ?)) LIMIT 1',
            [$g['email'], $g['phone']]
        );
        if ($existing !== null) {
            $userId = (int) $existing['id'];
            $note   = 'Linked to the existing parent account "' . $existing['username'] . '".';
        } else {
            $parts    = preg_split('/\s+/', trim((string) $g['full_name'])) ?: ['parent'];
            $last     = (string) array_pop($parts);
            $first    = $parts === [] ? $last : implode(' ', $parts);
            $base     = 'p.' . preg_replace('/[^a-z]/', '', strtolower(substr($first, 0, 1) . $last));
            $username = $base;
            for ($n = 2; Database::scalar('SELECT 1 FROM users WHERE username = ?', [$username]); $n++) {
                $username = $base . $n;
            }
            Database::statement(
                'INSERT INTO users (uuid, username, email, password_hash, first_name, last_name, phone, user_type, status, must_change_password)
                 VALUES (UUID(), ?, ?, ?, ?, ?, ?, "parent", "active", 1)',
                [$username, $g['email'], Importer::NO_PASSWORD, $first, $last, $g['phone']]
            );
            $userId = Database::lastInsertId();
            Database::statement('INSERT IGNORE INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = "guardian"', [$userId]);
            $note = 'Created the parent account "' . $username . '". Print its login slip from Data Imports > Login slips.';
        }
        Database::statement('UPDATE guardians SET user_id = ? WHERE id = ?', [$userId, $g['id']]);
        Audit::log('grant_access', 'students', 'guardians', (string) $g['id'], 'Parent portal access for ' . $g['full_name']);
        $this->success($note, $back);
    }

    public function revokeAccess(Request $request, string $guardianId): never
    {
        $this->authorize('students.edit');
        $this->verifyCsrf($request);
        $g = Database::selectOne('SELECT * FROM guardians WHERE id = ?', [(int) $guardianId]) ?? throw new HttpException(404);
        Database::statement('UPDATE guardians SET user_id = NULL WHERE id = ?', [$g['id']]);
        $this->success('Portal access removed for ' . $g['full_name'] . ' (the account stays for any other linked student).', '/students/' . $g['student_id'] . '/guardians');
    }

    public function destroy(Request $request, string $guardianId): never
    {
        $this->authorize('students.edit');
        $this->verifyCsrf($request);
        $g = Database::selectOne('SELECT * FROM guardians WHERE id = ?', [(int) $guardianId]) ?? throw new HttpException(404);
        Database::statement('DELETE FROM guardians WHERE id = ?', [$g['id']]);
        $this->success('Removed ' . $g['full_name'] . '.', '/students/' . $g['student_id'] . '/guardians');
    }
}
