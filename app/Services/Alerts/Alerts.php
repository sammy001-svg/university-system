<?php
declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\Notify;
use App\Core\Response;
use App\Core\Setting;
use Throwable;

/**
 * One call tells a person something on every channel they have: the in-app
 * bell, email (Admin > Settings > Notifications) and SMS for the events
 * chosen there. Email and SMS are queued in notification_log and sent after
 * the page has been delivered, so a slow gateway never slows anyone down;
 * database/send_alerts.php sends anything left over (run it from cron).
 */
final class Alerts
{
    public const EVENTS = [
        'payment_received'     => 'Payment received',
        'registration_complete'=> 'Registration complete',
        'class_scheduled'      => 'Online class scheduled',
        'class_cancelled'      => 'Online class cancelled',
        'exam_published'       => 'Online exam published',
        'results_released'     => 'Results released',
        'assignment_published' => 'Assignment set',
        'assignment_graded'    => 'Assignment marked',
        'document_issued'      => 'Document issued',
    ];

    private static bool $dispatchRegistered = false;

    public static function send(int $userId, string $event, string $title, string $message, string $link = '',
                                string $type = 'info', string $icon = 'bell'): void
    {
        self::sendMany([$userId], $event, $title, $message, $link, $type, $icon);
    }

    /** @param int[] $userIds */
    public static function sendMany(array $userIds, string $event, string $title, string $message, string $link = '',
                                    string $type = 'info', string $icon = 'bell'): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) {
            return;
        }
        Notify::sendMany($userIds, $title, $message, $link, $type, $icon);

        $email = (bool) Setting::get('alerts_email_enabled', true);
        $sms   = (bool) Setting::get('alerts_sms_enabled', false)
            && in_array($event, array_map('trim', explode(',', (string) Setting::get('alerts_sms_events', ''))), true);
        if (!$email && !$sms) {
            return;
        }
        try {
            $users = Database::select(
                'SELECT id, first_name, email, phone FROM users WHERE id IN (' . implode(',', $userIds) . ') AND deleted_at IS NULL'
            );
            $now   = date('Y-m-d H:i:s');
            $short = (string) Setting::get('institution_short_name', 'UNI');
            foreach ($users as $user) {
                if ($email && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                    $body = '<p>Dear ' . e($user['first_name']) . ',</p><p>' . nl2br(e($message)) . '</p>'
                          . ($link !== '' ? '<p><a href="' . e(Response::url($link)) . '">Open in the portal</a></p>' : '');
                    self::queue((int) $user['id'], 'email', $event, (string) $user['email'], $title, $body, $now);
                }
                if ($sms && trim((string) $user['phone']) !== '') {
                    $text = mb_substr($short . ': ' . $title . '. ' . $message, 0, 300);
                    self::queue((int) $user['id'], 'sms', $event, (string) $user['phone'], null, $text, $now);
                }
            }
        } catch (Throwable $e) {
            Logger::error('Could not queue alerts: ' . $e->getMessage());
        }
    }

    private static function queue(int $userId, string $channel, string $event, string $to, ?string $subject, string $body, string $now): void
    {
        Database::statement(
            'INSERT INTO notification_log (user_id, channel, event, recipient, subject, body, status, created_at) VALUES (?, ?, ?, ?, ?, ?, "queued", ?)',
            [$userId, $channel, $event, $to, $subject, $body, $now]
        );
        if (!self::$dispatchRegistered && PHP_SAPI !== 'cli') {
            self::$dispatchRegistered = true;
            register_shutdown_function(static function (): void {
                // Hand the page to the browser first, then talk to the gateways.
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                ignore_user_abort(true);
                self::dispatch(25);
            });
        }
    }

    /** Send queued email/SMS (oldest first). Returns the number sent. */
    public static function dispatch(int $limit = 100): int
    {
        $sent = 0;
        try {
            $rows = Database::select('SELECT * FROM notification_log WHERE status = "queued" AND attempts < 3 ORDER BY id LIMIT ' . max(1, $limit));
        } catch (Throwable) {
            return 0;
        }
        foreach ($rows as $row) {
            // Claim the row so two processes never send the same message.
            if (Database::statement('UPDATE notification_log SET attempts = attempts + 1 WHERE id = ? AND attempts = ? AND status = "queued"',
                    [$row['id'], $row['attempts']]) === 0) {
                continue;
            }
            try {
                if ($row['channel'] === 'email') {
                    if (!Mailer::send((string) $row['recipient'], (string) $row['subject'], (string) $row['body'])) {
                        throw new \RuntimeException('Mail server refused the message.');
                    }
                } else {
                    SmsGateway::send((string) $row['recipient'], (string) $row['body']);
                }
                Database::statement('UPDATE notification_log SET status = "sent", sent_at = ?, error = NULL WHERE id = ?', [date('Y-m-d H:i:s'), $row['id']]);
                $sent++;
            } catch (Throwable $e) {
                Database::statement(
                    'UPDATE notification_log SET status = IF(attempts >= 3, "failed", "queued"), error = ? WHERE id = ?',
                    [mb_substr($e->getMessage(), 0, 255), $row['id']]
                );
            }
        }
        return $sent;
    }
}
