<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Announcement extends Model
{
    protected string $table = 'announcements';
    protected array $fillable = [
        'title', 'body', 'audience', 'target_id', 'priority', 'attachment',
        'published_at', 'expires_at', 'created_by', 'status',
    ];
    protected array $searchable = ['a.title', 'a.body'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('announcements', 'a')
            ->select('a.*', 'CONCAT(u.first_name, " ", u.last_name) AS author_name')
            ->leftJoin('users u', 'u.id = a.created_by');
    }

    /** Published announcements visible to a given user. */
    public function feedFor(?array $user, int $limit = 10): array
    {
        if ($user === null) {
            return [];
        }
        $audiences = ['all'];
        $type      = $user['user_type'] ?? '';
        if ($type === 'student') {
            $audiences[] = 'students';
        }
        if (in_array($type, ['staff', 'lecturer', 'admin'], true)) {
            $audiences[] = 'staff';
        }
        if ($type === 'lecturer') {
            $audiences[] = 'lecturers';
        }

        $placeholders = implode(',', array_fill(0, count($audiences), '?'));
        $params       = $audiences;

        return Database::select(
            "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
               FROM announcements a
          LEFT JOIN users u ON u.id = a.created_by
              WHERE a.status = 'published'
                AND a.audience IN ({$placeholders})
                AND (a.published_at IS NULL OR a.published_at <= NOW())
                AND (a.expires_at IS NULL OR a.expires_at >= NOW())
              ORDER BY FIELD(a.priority,'urgent','high','normal','low'), a.published_at DESC
              LIMIT " . max(1, $limit),
            $params
        );
    }

    public function incrementViews(int $id): void
    {
        Database::statement('UPDATE announcements SET views = views + 1 WHERE id = ?', [$id]);
    }
}
