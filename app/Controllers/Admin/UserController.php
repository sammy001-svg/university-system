<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Acl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Hash;
use App\Core\Mailer;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Session;
use App\Models\Role;
use App\Models\User;

final class UserController extends Controller
{
    private User $users;
    private Role $roles;

    public function __construct()
    {
        $this->users = new User();
        $this->roles = new Role();
    }

    public function index(Request $request): string
    {
        $this->authorize('users.view');

        $query = $this->users->listing()
            ->search((string) $request->query('q', ''), $this->users->searchable());

        if ($request->filled('user_type')) {
            $query->where('u.user_type', $request->query('user_type'));
        }
        if ($request->filled('status')) {
            $query->where('u.status', $request->query('status'));
        }
        if ($request->filled('role_id')) {
            $query->whereRaw(
                'EXISTS (SELECT 1 FROM user_roles ur2 WHERE ur2.user_id = u.id AND ur2.role_id = ?)',
                [$request->query('role_id')]
            );
        }

        $result = $query->orderBy('u.created_at', 'DESC')
            ->paginate($this->page($request), $this->perPage($request));

        return $this->view('admin.users.index', [
            'pageTitle' => 'User Accounts',
            'result'    => $result,
            'roles'     => $this->roles->options('name', 'level'),
        ]);
    }

    public function create(Request $request): string
    {
        $this->authorize('users.create');
        return $this->view('admin.users.form', [
            'pageTitle' => 'New user account',
            'user'      => [],
            'roles'     => $this->roles->withCounts(),
            'userRoles' => [],
            'isNew'     => true,
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('users.create');
        $this->verifyCsrf($request);

        $data = $this->validate($request, [
            'first_name' => 'required|max:80',
            'last_name'  => 'required|max:80',
            'email'      => 'required|email|max:150|unique:users,email',
            'username'   => 'required|alpha_dash|min:3|max:60|unique:users,username',
            'user_type'  => 'required|in:admin,staff,lecturer,student,parent',
            'phone'      => 'nullable|phone',
            'gender'     => 'nullable|in:male,female,other',
            'status'     => 'required|in:active,inactive,suspended,pending',
        ]);

        $roleIds  = array_map('intval', $request->array('roles'));
        $password = trim((string) $request->input('password', ''));
        if ($password === '') {
            $password = Hash::tempPassword();
        }

        $data['title']                = $request->input('title');
        $data['other_name']           = $request->input('other_name');
        $data['date_of_birth']        = $request->input('date_of_birth') ?: null;
        $data['must_change_password'] = 1;

        $userId = $this->users->createAccount($data, $roleIds, $password, Auth::id());
        Audit::created('user', $userId, ['username' => $data['username'], 'roles' => $roleIds], 'users');

        $body = '<p>Hello ' . e($data['first_name']) . ',</p>'
              . '<p>An account has been created for you on the university management system.</p>'
              . '<p><strong>Username:</strong> ' . e($data['username']) . '<br>'
              . '<strong>Temporary password:</strong> ' . e($password) . '</p>'
              . '<p>You will be asked to change this password when you first sign in.</p>';
        Mailer::send((string) $data['email'], 'Your account details', $body);

        Session::flash('success', 'User created. Temporary password: ' . $password);
        $this->redirect('/admin/users');
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('users.edit');
        $user = $this->findOrFail($this->users, (int) $id, 'User');

        return $this->view('admin.users.form', [
            'pageTitle' => 'Edit user account',
            'user'      => $user,
            'roles'     => $this->roles->withCounts(),
            'userRoles' => $this->users->roleIds((int) $id),
            'isNew'     => false,
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('users.edit');
        $this->verifyCsrf($request);

        $user = $this->findOrFail($this->users, (int) $id, 'User');

        $data = $this->validate($request, [
            'first_name' => 'required|max:80',
            'last_name'  => 'required|max:80',
            'email'      => 'required|email|max:150|unique:users,email,' . $id,
            'username'   => 'required|alpha_dash|min:3|max:60|unique:users,username,' . $id,
            'user_type'  => 'required|in:admin,staff,lecturer,student,parent',
            'phone'      => 'nullable|phone',
            'gender'     => 'nullable|in:male,female,other',
            'status'     => 'required|in:active,inactive,suspended,pending',
        ]);

        $data['title']         = $request->input('title');
        $data['other_name']    = $request->input('other_name');
        $data['date_of_birth'] = $request->input('date_of_birth') ?: null;

        // Nobody may lock themselves out of their own account.
        if ((int) $id === Auth::id() && $data['status'] !== 'active') {
            $this->error('You cannot deactivate your own account.', '/admin/users/' . $id . '/edit');
        }

        $this->users->update((int) $id, $data);

        if ($request->has('roles') && Auth::can('roles.assign')) {
            $this->guardSuperAdmin((int) $id, array_map('intval', $request->array('roles')));
            Acl::syncRoles((int) $id, array_map('intval', $request->array('roles')), Auth::id());
        }

        Audit::updated('user', (int) $id, $user, $data, 'users');
        $this->success('User account updated.', '/admin/users');
    }

    /** The last super administrator must keep the role. */
    private function guardSuperAdmin(int $userId, array $newRoleIds): void
    {
        $superId = (int) Database::scalar("SELECT id FROM roles WHERE slug = 'super-admin'");
        if ($superId === 0 || in_array($superId, $newRoleIds, true)) {
            return;
        }
        $held = (int) Database::scalar('SELECT COUNT(*) FROM user_roles WHERE role_id = ? AND user_id = ?', [$superId, $userId]);
        if ($held === 0) {
            return;
        }
        $total = (int) Database::scalar('SELECT COUNT(*) FROM user_roles WHERE role_id = ?', [$superId]);
        if ($total <= 1) {
            $this->error('At least one super administrator must remain.', '/admin/users/' . $userId . '/edit');
        }
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('users.delete');
        $this->verifyCsrf($request);

        if ((int) $id === Auth::id()) {
            $this->error('You cannot delete your own account.', '/admin/users');
        }
        $user = $this->findOrFail($this->users, (int) $id, 'User');
        $this->guardSuperAdmin((int) $id, []);

        $this->users->delete((int) $id);
        Audit::deleted('user', (int) $id, $user, 'users');
        $this->success('User account removed.', '/admin/users');
    }

    public function resetPassword(Request $request, string $id): never
    {
        $this->authorize('users.reset');
        $this->verifyCsrf($request);

        $user     = $this->findOrFail($this->users, (int) $id, 'User');
        $password = Hash::tempPassword();
        $this->users->setPassword((int) $id, $password, true);

        Audit::log('password_reset', 'users', 'users', $id, 'Administrator reset the password');
        Mailer::send(
            (string) $user['email'],
            'Your password has been reset',
            '<p>Hello ' . e($user['first_name']) . ',</p><p>Your new temporary password is <strong>'
            . e($password) . '</strong>. You will be asked to change it at your next sign-in.</p>'
        );

        Session::flash('success', 'Password reset. Temporary password: ' . $password);
        $this->back('/admin/users');
    }

    public function toggleStatus(Request $request, string $id): never
    {
        $this->authorize('users.edit');
        $this->verifyCsrf($request);

        if ((int) $id === Auth::id()) {
            $this->error('You cannot change the status of your own account.', '/admin/users');
        }
        $user      = $this->findOrFail($this->users, (int) $id, 'User');
        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';

        $this->users->update((int) $id, ['status' => $newStatus]);
        Audit::log('status_change', 'users', 'users', $id, 'Status changed to ' . $newStatus);

        $this->success('Account is now ' . $newStatus . '.', '/admin/users');
    }
}
