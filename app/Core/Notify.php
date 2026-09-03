<?php
declare(strict_types=1);

namespace App\Core;

final class Notify
{
    public static function send(int $userId, string $title, string $message = '', string $link = '', string $type = 'info', string $icon = 'bell'): void
    {
        Database::statement(
            'INSERT INTO notifications (user_id, type, icon, title, message, link) VALUES (?, ?, ?, ?, ?, ?)',
            [$userId, $type, $icon, $title, $message, $link]
        );
    }

    public static function sendMany(array $userIds, string $title, string $message = '', string $link = '', string $type = 'info', string $icon = 'bell'): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId > 0) {
                self::send($userId, $title, $message, $link, $type, $icon);
            }
        }
    }

    /** Notify every user holding a given role. */
    public static function toRole(string $roleSlug, string $title, string $message = '', string $link = ''): void
    {
        $ids = array_column(
            Database::select(
                'SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = ?',
                [$roleSlug]
            ),
            'user_id'
        );
        self::sendMany($ids, $title, $message, $link);
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public static function latest(int $userId, int $limit = 8): array
    {
        return Database::select(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . max(1, $limit),
            [$userId]
        );
    }

    public static function markRead(int $notificationId, int $userId): void
    {
        Database::statement(
            'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [$notificationId, $userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        Database::statement('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
