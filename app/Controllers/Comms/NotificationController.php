<?php
declare(strict_types=1);

namespace App\Controllers\Comms;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Models\Notification;

final class NotificationController extends Controller
{
    public function index(Request $request): string
    {
        $notifications = Database::select(
            'SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 50',
            [Auth::id()]
        );
        return $this->view('comms.notifications', ['pageTitle' => 'Notifications', 'notifications' => $notifications]);
    }

    public function readAll(Request $request): never
    {
        $this->verifyCsrf($request);
        Database::statement("UPDATE notifications SET is_read=1 WHERE user_id=?", [Auth::id()]);
        $this->success('All notifications marked as read.', '/notifications');
    }
}
