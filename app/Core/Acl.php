<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Role/permission resolution.
 *
 * Effective permissions = union of role permissions
 *                       + per-user "allow" overrides
 *                       - per-user "deny" overrides.
 * The `super-admin` role short-circuits every check.
 */
final class Acl
{
    public const SUPER_ADMIN = 'super-admin';

    private static array $cache = [];

    /** @return array<int,string> permission slugs */
    public static function permissionsFor(int $userId): array
    {
        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $rows = Database::select(
            'SELECT DISTINCT p.slug
               FROM permissions p
               JOIN role_permissions rp ON rp.permission_id = p.id
               JOIN user_roles ur       ON ur.role_id = rp.role_id
              WHERE ur.user_id = ?',
            [$userId]
        );
        $slugs = array_column($rows, 'slug');

        $overrides = Database::select(
            'SELECT p.slug, up.effect
               FROM user_permissions up
               JOIN permissions p ON p.id = up.permission_id
              WHERE up.user_id = ?',
            [$userId]
        );
        foreach ($overrides as $override) {
            if ($override['effect'] === 'allow') {
                $slugs[] = $override['slug'];
            } else {
                $slugs = array_diff($slugs, [$override['slug']]);
            }
        }

        return self::$cache[$userId] = array_values(array_unique($slugs));
    }

    /** @return array<int,array<string,mixed>> */
    public static function rolesFor(int $userId): array
    {
        return Database::select(
            'SELECT r.id, r.name, r.slug, r.level
               FROM roles r
               JOIN user_roles ur ON ur.role_id = r.id
              WHERE ur.user_id = ?
              ORDER BY r.level ASC',
            [$userId]
        );
    }

    public static function hasRole(int $userId, string|array $roles): bool
    {
        $roles = (array) $roles;
        $slugs = array_column(self::rolesFor($userId), 'slug');
        return array_intersect($roles, $slugs) !== [];
    }

    public static function isSuperAdmin(int $userId): bool
    {
        return self::hasRole($userId, self::SUPER_ADMIN);
    }

    /**
     * Check one or more permissions. Supports wildcards: "students.*".
     */
    public static function can(int $userId, string|array $permissions, bool $requireAll = false): bool
    {
        if (self::isSuperAdmin($userId)) {
            return true;
        }
        $held        = self::permissionsFor($userId);
        $permissions = (array) $permissions;

        foreach ($permissions as $permission) {
            $matched = in_array($permission, $held, true);

            if (!$matched && str_ends_with($permission, '.*')) {
                $prefix = substr($permission, 0, -1);
                foreach ($held as $slug) {
                    if (str_starts_with($slug, $prefix)) {
                        $matched = true;
                        break;
                    }
                }
            }
            if ($requireAll && !$matched) {
                return false;
            }
            if (!$requireAll && $matched) {
                return true;
            }
        }
        return $requireAll;
    }

    /** Highest authority (lowest level number) held by the user. */
    public static function level(int $userId): int
    {
        $roles = self::rolesFor($userId);
        if ($roles === []) {
            return 99;
        }
        return (int) min(array_column($roles, 'level'));
    }

    public static function syncRoles(int $userId, array $roleIds, ?int $actorId = null): void
    {
        Database::statement('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
        foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
            if ($roleId <= 0) {
                continue;
            }
            Database::statement(
                'INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?)',
                [$userId, $roleId, $actorId]
            );
        }
        self::flush($userId);
    }

    public static function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        Database::statement('DELETE FROM role_permissions WHERE role_id = ?', [$roleId]);
        foreach (array_unique(array_map('intval', $permissionIds)) as $permissionId) {
            if ($permissionId <= 0) {
                continue;
            }
            Database::statement(
                'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)',
                [$roleId, $permissionId]
            );
        }
        self::flush();
    }

    public static function flush(?int $userId = null): void
    {
        if ($userId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$userId]);
        }
    }

    /** All permissions grouped by module, for the role editor. */
    public static function grouped(): array
    {
        $rows = Database::select('SELECT id, name, slug, module, description FROM permissions ORDER BY module, slug');
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['module']][] = $row;
        }
        return $out;
    }
}
