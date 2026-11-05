<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Services\Alerts\Alerts;
use App\Services\Security\BackupService;
use RuntimeException;
use Throwable;

/** Admin > Sessions, Backups and Alerts log. */
final class SecurityAdminController extends Controller
{
    /* ---------------- Signed-in sessions ---------------- */

    public function sessions(Request $request): string
    {
        $this->authorize('users.view');
        return $this->view('admin.sessions', [
            'pageTitle' => 'Signed-in sessions',
            'sessions'  => Database::select(
                'SELECT us.*, u.username, u.first_name, u.last_name, u.user_type, u.two_factor_enabled_at IS NOT NULL AS has_2fa
                   FROM user_sessions us JOIN users u ON u.id = us.user_id
                  WHERE us.revoked_at IS NULL AND us.last_seen_at > ?
                  ORDER BY us.last_seen_at DESC LIMIT 500',
                [date('Y-m-d H:i:s', time() - 86400 * 7)]
            ),
            'twoFactor' => Database::select('SELECT user_type, SUM(two_factor_enabled_at IS NOT NULL) AS on_count, COUNT(*) AS total
                                               FROM users WHERE deleted_at IS NULL AND status = "active" AND user_type IN ("admin","staff","lecturer") GROUP BY user_type'),
        ]);
    }

    public function revokeSession(Request $request, string $id): never
    {
        $this->authorize('users.edit');
        $this->verifyCsrf($request);
        $row = Database::selectOne('SELECT user_id FROM user_sessions WHERE id = ?', [(int) $id]);
        if ($row !== null) {
            $all = $request->input('all') === '1';
            Database::statement(
                $all ? 'UPDATE user_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL'
                     : 'UPDATE user_sessions SET revoked_at = ? WHERE id = ?',
                [date('Y-m-d H:i:s'), $all ? $row['user_id'] : (int) $id]
            );
            Audit::log('session_revoked', 'auth', 'users', (string) $row['user_id'], $all ? 'Signed out on every device by an administrator' : 'Session signed out by an administrator');
        }
        $this->success('Signed out.', '/admin/sessions');
    }

    /* ---------------- Backups ---------------- */

    public function backups(Request $request): string
    {
        $this->authorize('settings.manage');
        return $this->view('admin.backups', ['pageTitle' => 'Backups', 'backups' => BackupService::all(), 'dir' => BackupService::dir()]);
    }

    public function createBackup(Request $request): never
    {
        $this->authorize('settings.manage');
        $this->verifyCsrf($request);
        try {
            $name = BackupService::create();
        } catch (Throwable $e) {
            $this->error('The backup failed: ' . $e->getMessage(), '/admin/backups');
        }
        Audit::log('backup', 'admin', 'backups', $name, 'Database backup created');
        $this->success('Backup ' . $name . ' created.', '/admin/backups');
    }

    public function downloadBackup(Request $request, string $name): never
    {
        $this->authorize('settings.manage');
        try {
            $path = BackupService::path($name);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/admin/backups');
        }
        Audit::log('backup_download', 'admin', 'backups', $name, 'Database backup downloaded');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/gzip');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: attachment; filename="' . $name . '"');
        readfile($path);
        exit;
    }

    public function deleteBackup(Request $request, string $name): never
    {
        $this->authorize('settings.manage');
        $this->verifyCsrf($request);
        try {
            @unlink(BackupService::path($name));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/admin/backups');
        }
        Audit::log('backup_delete', 'admin', 'backups', $name, 'Database backup deleted');
        $this->success('Backup deleted.', '/admin/backups');
    }

    /* ---------------- Alerts log ---------------- */

    public function alerts(Request $request): string
    {
        $this->authorize('settings.manage');
        $status = (string) $request->query('status', '');
        $rows = Database::select(
            'SELECT n.*, CONCAT(u.first_name, " ", u.last_name) AS name FROM notification_log n LEFT JOIN users u ON u.id = n.user_id'
            . ($status !== '' ? ' WHERE n.status = ?' : '') . ' ORDER BY n.id DESC LIMIT 300',
            $status !== '' ? [$status] : []
        );
        return $this->view('admin.alerts', [
            'pageTitle' => 'Email and SMS alerts',
            'rows'      => $rows,
            'counts'    => array_column(Database::select('SELECT status, COUNT(*) AS n FROM notification_log GROUP BY status'), 'n', 'status'),
            'status'    => $status,
            'smsDriver' => (string) \App\Core\Config::get('sms.driver', 'log'),
            'mailOn'    => (bool) \App\Core\Config::get('mail.enabled', false),
        ]);
    }

    public function sendAlerts(Request $request): never
    {
        $this->authorize('settings.manage');
        $this->verifyCsrf($request);
        Database::statement('UPDATE notification_log SET status = "queued", attempts = 0 WHERE status = "failed" AND ? = "1"', [(string) $request->input('retry_failed')]);
        $sent = Alerts::dispatch(200);
        $this->success($sent . ' message(s) sent.', '/admin/alerts');
    }
}
