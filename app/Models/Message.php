<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Message extends Model
{
    protected string $table = 'messages';
    protected bool $timestamps = false;
    protected array $fillable = [
        'sender_id', 'recipient_id', 'parent_id', 'subject', 'body', 'attachment',
        'read_at', 'deleted_by_sender', 'deleted_by_recipient',
    ];

    public function inbox(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->query('m')
            ->select(
                'm.*', 'u.first_name', 'u.last_name', 'u.avatar', 'u.user_type', 'u.email'
            )
            ->join('users u', 'u.id = m.sender_id')
            ->where('m.recipient_id', $userId)
            ->where('m.deleted_by_recipient', 0)
            ->orderBy('m.created_at', 'DESC')
            ->paginate($page, $perPage);
    }

    public function sent(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->query('m')
            ->select('m.*', 'u.first_name', 'u.last_name', 'u.avatar', 'u.user_type')
            ->join('users u', 'u.id = m.recipient_id')
            ->where('m.sender_id', $userId)
            ->where('m.deleted_by_sender', 0)
            ->orderBy('m.created_at', 'DESC')
            ->paginate($page, $perPage);
    }

    public function thread(int $messageId, int $userId): array
    {
        $message = Database::selectOne(
            'SELECT * FROM messages WHERE id = ? AND (sender_id = ? OR recipient_id = ?)',
            [$messageId, $userId, $userId]
        );
        if ($message === null) {
            return [];
        }
        $rootId = $message['parent_id'] ?? $message['id'];
        return Database::select(
            'SELECT m.*, u.first_name, u.last_name, u.avatar
               FROM messages m JOIN users u ON u.id = m.sender_id
              WHERE m.id = ? OR m.parent_id = ?
              ORDER BY m.created_at',
            [$rootId, $rootId]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND read_at IS NULL AND deleted_by_recipient = 0',
            [$userId]
        );
    }

    public function markRead(int $messageId, int $userId): void
    {
        Database::statement(
            'UPDATE messages SET read_at = NOW() WHERE id = ? AND recipient_id = ? AND read_at IS NULL',
            [$messageId, $userId]
        );
    }
}
