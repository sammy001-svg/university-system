<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Applies database/seeds/access.php - the permissions the software checks and
 * the roles a university fills.
 *
 * On a fresh install it runs authoritatively: a system role's grants are wiped
 * and rebuilt from the catalogue. On a running database it runs additively:
 * new permissions, roles and grants are added, and nothing an administrator
 * has changed by hand is taken away.
 */
final class AccessSync
{
    /** @return array{modules:array<string,array<string,string>>, roles:list<array>} */
    public static function catalogue(string $basePath): array
    {
        /** @var array{modules:array<string,array<string,string>>, roles:list<array>} $catalogue */
        $catalogue = require $basePath . '/database/seeds/access.php';
        return $catalogue;
    }

    /**
     * @return array{permissions:int, permissions_added:int, roles:int, roles_added:int, grants_added:int}
     */
    public static function apply(PDO $pdo, string $basePath, bool $authoritative = false): array
    {
        $catalogue = self::catalogue($basePath);

        /* ---------------- Permissions ---------------- */
        $before = $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
        $insert = $pdo->prepare(
            'INSERT INTO permissions (name, slug, module, description) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module), description = VALUES(description)'
        );
        $permissions = 0;
        foreach ($catalogue['modules'] as $module => $actions) {
            foreach ($actions as $action => $description) {
                $insert->execute([
                    ucfirst($action) . ' ' . str_replace('_', ' ', $module),
                    $module . '.' . $action,
                    $module,
                    $description,
                ]);
                $permissions++;
            }
        }
        $permissionsAdded = (int) $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn() - (int) $before;

        /* ---------------- Roles ---------------- */
        $slugToId = $pdo->query('SELECT slug, id FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);
        $upsert   = $pdo->prepare(
            'INSERT INTO roles (name, slug, description, level, is_system) VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), level = VALUES(level), is_system = 1'
        );
        $findRole = $pdo->prepare('SELECT id FROM roles WHERE slug = ?');
        $clear    = $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?');
        $link     = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');

        $rolesBefore  = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
        $grantsBefore = (int) $pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn();

        foreach ($catalogue['roles'] as [$name, $slug, $level, $description, $grants]) {
            $upsert->execute([$name, $slug, $description, $level]);
            $findRole->execute([$slug]);
            $roleId = (int) $findRole->fetchColumn();

            if ($authoritative) {
                $clear->execute([$roleId]);
            }
            foreach (self::expand($grants, $slugToId) as $permissionId) {
                $link->execute([$roleId, $permissionId]);
            }
        }

        return [
            'permissions'       => $permissions,
            'permissions_added' => $permissionsAdded,
            'roles'             => count($catalogue['roles']),
            'roles_added'       => (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() - $rolesBefore,
            'grants_added'      => (int) $pdo->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn() - $grantsBefore,
        ];
    }

    /**
     * Turn a role's grant patterns into permission ids.
     * "*" is everything, "module.*" every action of a module, otherwise an exact slug.
     *
     * @param  list<string>        $grants
     * @param  array<string,int|string> $slugToId
     * @return list<int>
     */
    private static function expand(array $grants, array $slugToId): array
    {
        $ids = [];
        foreach ($grants as $pattern) {
            if ($pattern === '*') {
                return array_map('intval', array_values($slugToId));
            }
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                foreach ($slugToId as $slug => $id) {
                    if (str_starts_with((string) $slug, $prefix)) {
                        $ids[] = (int) $id;
                    }
                }
                continue;
            }
            if (isset($slugToId[$pattern])) {
                $ids[] = (int) $slugToId[$pattern];
            }
        }
        return array_values(array_unique($ids));
    }
}
