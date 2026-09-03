<?php
declare(strict_types=1);

namespace App\Controllers\Comms;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;

final class MessageController extends Controller
{
    public function inbox(Request $request): string
    {
        $messages = Database::select(
            'SELECT m.*, u.first_name, u.last_name FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.recipient_id=? ORDER BY m.created_at DESC LIMIT 50',
            [Auth::id()]
        );
        return $this->view('comms.messages.inbox', ['pageTitle' => 'Inbox', 'messages' => $messages]);
    }

    public function compose(Request $request): string
    {
        $users = Database::select("SELECT id, CONCAT(first_name,' ',last_name,' (',username,')') AS label FROM users WHERE id != ? AND deleted_at IS NULL ORDER BY last_name", [Auth::id()]);
        return $this->view('comms.messages.compose', ['pageTitle' => 'Compose Message', 'users' => $users]);
    }

    public function send(Request $request): never
    {
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'recipient_id' => 'required|integer|exists:users,id',
            'subject'      => 'required|max:200',
            'body'         => 'required',
        ]);
        $data['sender_id'] = Auth::id();
        Database::statement(
            'INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?,?,?,?)',
            [$data['sender_id'], $data['recipient_id'], $data['subject'], $data['body']]
        );
        $this->success('Message sent.', '/messages');
    }

    public function show(Request $request, string $id): string
    {
        $message = Database::selectOne('SELECT * FROM messages WHERE id=? AND (recipient_id=? OR sender_id=?)', [(int)$id, Auth::id(), Auth::id()]);
        if (!$message) { \App\Core\Response::redirect('/messages'); }
        if ((int)$message['recipient_id'] === (int)Auth::id() && !$message['read_at']) {
            Database::statement('UPDATE messages SET read_at=NOW() WHERE id=?', [(int)$id]);
        }
        return $this->view('comms.messages.show', ['pageTitle' => $message['subject'], 'message' => $message]);
    }
}
