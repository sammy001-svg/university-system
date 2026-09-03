<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Acl;
use App\Core\Audit;
use App\Core\Controller;
use App\Core\Request;
use App\Models\Permission;
use App\Models\Role;

final class RoleController extends Controller
{
    private Role $roles;

    public function __construct()
    {
        $this->roles = new Role();
    }

    public function index(Request $request): string
    {
        $this->authorize('roles.view');
        return $this->view('admin.roles.index', [
            'pageTitle' => 'Roles and Permissions',
            'roles'     => $this->roles->withCounts(),
        ]);
    }

    public function create(Request $request): string
    {
        $this->authorize('roles.create');
        return $this->view('admin.roles.form', [
            'pageTitle' => 'New role',
            'role'      => [],
            'isNew'     => true,
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('roles.create');
        $this->verifyCsrf($request);

        $data = $this->validate($request, [
            'name'        => 'required|max:80|unique:roles,name',
            'slug'        => 'nullable|alpha_dash|max:80|unique:roles,slug',
            'description' => 'nullable|max:255',
            'level'       => 'required|integer|between:1,99',
        ]);

        $data['slug'] = $data['slug'] ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', (string) $data['name']));
        $id = $this->roles->create($data);
        Audit::created('role', $id, $data, 'roles');

        $this->success('Role created. Now assign its permissions.', '/admin/roles/' . $id . '/permissions');
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('roles.edit');
        return $this->view('admin.roles.form', [
            'pageTitle' => 'Edit role',
            'role'      => $this->findOrFail($this->roles, (int) $id, 'Role'),
            'isNew'     => false,
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('roles.edit');
        $this->verifyCsrf($request);

        $role = $this->findOrFail($this->roles, (int) $id, 'Role');
        $data = $this->validate($request, [
            'name'        => 'required|max:80|unique:roles,name,' . $id,
            'slug'        => 'nullable|alpha_dash|max:80|unique:roles,slug,' . $id,
            'description' => 'nullable|max:255',
            'level'       => 'required|integer|between:1,99',
        ]);

        // System role slugs are referenced in code and must not change.
        if ((int) $role['is_system'] === 1) {
            unset($data['slug']);
        }

        $this->roles->update((int) $id, $data);
        Audit::updated('role', (int) $id, $role, $data, 'roles');
        $this->success('Role updated.', '/admin/roles');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('roles.delete');
        $this->verifyCsrf($request);

        $role = $this->findOrFail($this->roles, (int) $id, 'Role');
        if ((int) $role['is_system'] === 1) {
            $this->error('System roles cannot be deleted.', '/admin/roles');
        }
        if ($this->roles->isInUse((int) $id)) {
            $this->error('This role is still assigned to users; reassign them first.', '/admin/roles');
        }

        $this->roles->delete((int) $id);
        Audit::deleted('role', (int) $id, $role, 'roles');
        $this->success('Role deleted.', '/admin/roles');
    }

    public function permissions(Request $request, string $id): string
    {
        $this->authorize('roles.assign');
        return $this->view('admin.roles.permissions', [
            'pageTitle'   => 'Role permissions',
            'role'        => $this->findOrFail($this->roles, (int) $id, 'Role'),
            'grouped'     => Acl::grouped(),
            'assigned'    => $this->roles->permissionIds((int) $id),
        ]);
    }

    public function savePermissions(Request $request, string $id): never
    {
        $this->authorize('roles.assign');
        $this->verifyCsrf($request);

        $role = $this->findOrFail($this->roles, (int) $id, 'Role');
        if ($role['slug'] === Acl::SUPER_ADMIN) {
            $this->error('The super administrator role always holds every permission.', '/admin/roles');
        }

        $permissionIds = array_map('intval', $request->array('permissions'));
        Acl::syncRolePermissions((int) $id, $permissionIds);
        Audit::log('permissions_sync', 'roles', 'roles', $id, count($permissionIds) . ' permissions assigned');

        $this->success('Permissions updated for ' . $role['name'] . '.', '/admin/roles');
    }
}
