<?php
declare(strict_types=1);

namespace App\Controllers\Comms;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

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

    /** Messages this person has sent. One row per recipient, newest first. */
    public function sent(Request $request): string
    {
        $messages = Database::select(
            'SELECT m.*, u.first_name, u.last_name
               FROM messages m
               JOIN users u ON u.id = m.recipient_id
              WHERE m.sender_id = ?
              ORDER BY m.created_at DESC
              LIMIT 50',
            [Auth::id()]
        );
        return $this->view('comms.messages.sent', ['pageTitle' => 'Sent Messages', 'messages' => $messages]);
    }

    public function compose(Request $request): string
    {
        $users = Database::select(
            "SELECT u.id, u.first_name, u.last_name, u.username, u.email, u.user_type,
                    COALESCE(
                        (SELECT s.admission_number FROM students s WHERE s.user_id = u.id LIMIT 1),
                        (SELECT sf.staff_number FROM staff sf WHERE sf.user_id = u.id LIMIT 1),
                        ''
                    ) AS code
               FROM users u
              WHERE u.id != ? AND u.deleted_at IS NULL
              ORDER BY u.last_name, u.first_name",
            [Auth::id()]
        );
        return $this->view('comms.messages.compose', ['pageTitle' => 'Compose Message', 'users' => $users]);
    }

    public function send(Request $request): never
    {
        $this->verifyCsrf($request);
        $rawIds = $request->array('recipient_ids');
        if ($rawIds === [] && $request->filled('recipient_id')) {
            $rawIds = [$request->input('recipient_id')];
        }
        $candidateIds = array_values(array_unique(array_filter(
            array_map('intval', $rawIds),
            static fn (int $id): bool => $id > 0 && $id !== (int) Auth::id()
        )));

        $data = $this->validate($request, [
            'subject' => 'required|max:200',
            'body'    => 'required',
        ]);

        if ($candidateIds === []) {
            Session::keepOldInput($request->all());
            $this->error('Please select at least one recipient.', '/messages/compose');
        }

        $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
        $validIds = array_map('intval', array_column(Database::select(
            "SELECT id FROM users WHERE id IN ({$placeholders}) AND deleted_at IS NULL",
            $candidateIds
        ), 'id'));

        if ($validIds === []) {
            Session::keepOldInput($request->all());
            $this->error('Please select at least one valid recipient.', '/messages/compose');
        }

        $senderId = (int) Auth::id();
        Database::transaction(static function () use ($senderId, $validIds, $data): void {
            foreach ($validIds as $recipientId) {
                Database::statement(
                    'INSERT INTO messages (sender_id, recipient_id, subject, body) VALUES (?, ?, ?, ?)',
                    [$senderId, $recipientId, $data['subject'], $data['body']]
                );
            }
        });
        Session::clearOldInput();
        $count = count($validIds);
        $this->success($count === 1 ? 'Message sent.' : "Message sent to {$count} recipients.", '/messages');
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
