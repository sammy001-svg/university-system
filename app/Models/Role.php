<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Role extends Model
{
    protected string $table = 'roles';
    protected array $fillable = ['name', 'slug', 'description', 'level', 'is_system'];
    protected array $searchable = ['name', 'slug', 'description'];

    public function withCounts(): array
    {
        return Database::select(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS users_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permissions_count
               FROM roles r
              ORDER BY r.level ASC, r.name ASC'
        );
    }

    public function permissionIds(int $roleId): array
    {
        return array_map(
            'intval',
            array_column(
                Database::select('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$roleId]),
                'permission_id'
            )
        );
    }

    public function isInUse(int $roleId): bool
    {
        return (int) Database::scalar('SELECT COUNT(*) FROM user_roles WHERE role_id = ?', [$roleId]) > 0;
    }
}
