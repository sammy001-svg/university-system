<?php
declare(strict_types=1);

namespace App\Controllers\Comms;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Notify;
use App\Core\Request;
use App\Models\SupportTicket;

final class TicketController extends Controller
{
    private SupportTicket $model;
    public function __construct() { $this->model = new SupportTicket(); }

    /** Helpdesk staff (tickets.edit) see every ticket; everyone else sees only their own. */
    private function staffView(): bool
    {
        return Auth::can('tickets.edit');
    }

    public function index(Request $request): string
    {
        $this->authorize('tickets.view');
        $tickets = Database::select(
            'SELECT t.*, u.first_name, u.last_name FROM support_tickets t JOIN users u ON u.id = t.user_id'
            . ($this->staffView() ? '' : ' WHERE t.user_id = ?') . ' ORDER BY t.created_at DESC LIMIT 200',
            $this->staffView() ? [] : [Auth::id()]
        );
        return $this->view('comms.tickets.index', ['pageTitle' => 'Support Tickets', 'tickets' => $tickets]);
    }

    private function ticket(int $id): array
    {
        $ticket = Database::selectOne('SELECT t.*, u.first_name, u.last_name FROM support_tickets t JOIN users u ON u.id = t.user_id WHERE t.id = ?', [$id]);
        if (!$ticket) { throw new HttpException(404); }
        if (!$this->staffView() && (int) $ticket['user_id'] !== (int) Auth::id()) { throw new HttpException(403); }
        return $ticket;
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('tickets.view');
        $ticket  = $this->ticket((int) $id);
        $replies = Database::select(
            'SELECT r.*, u.first_name, u.last_name FROM ticket_replies r JOIN users u ON u.id = r.user_id WHERE r.ticket_id = ?'
            . ($this->staffView() ? '' : ' AND r.is_internal = 0') . ' ORDER BY r.created_at', [(int) $id]);
        return $this->view('comms.tickets.show', ['pageTitle' => 'Ticket ' . $ticket['ticket_number'], 'ticket' => $ticket, 'replies' => $replies,
            'canReply' => !in_array($ticket['status'], ['closed'], true)]);
    }

    public function create(Request $request): string
    {
        return $this->view('comms.tickets.create', ['pageTitle' => 'New Support Ticket']);
    }

    public function store(Request $request): never
    {
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'subject'  => 'required|max:200',
            'body'     => 'required',
            'category' => 'required|in:academic,finance,ict,hostel,library,general,complaint',
            'priority' => 'required|in:low,normal,high,urgent',
        ]);
        $data['user_id']        = Auth::id();
        $data['status']         = 'open';
        $data['ticket_number']  = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $id = $this->model->create($data);
        $this->success('Ticket submitted. Ref: ' . $data['ticket_number'], '/services/tickets/' . $id);
    }

    public function reply(Request $request, string $id): never
    {
        $this->authorize('tickets.view');
        $this->verifyCsrf($request);
        $ticket = $this->ticket((int) $id);   // the requester may reply on their own ticket; helpdesk staff on any
        $data = $this->validate($request, ['body' => 'required']);
        Database::statement('INSERT INTO ticket_replies (ticket_id, user_id, body) VALUES (?,?,?)', [(int)$id, Auth::id(), $data['body']]);
        // A reply from the helpdesk moves the ticket on; a reply from the requester reopens a resolved one.
        $status = $this->staffView() ? 'in_progress' : ($ticket['status'] === 'resolved' ? 'reopened' : $ticket['status']);
        Database::statement('UPDATE support_tickets SET status = ?, updated_at = ? WHERE id = ?', [$status, date('Y-m-d H:i:s'), (int) $id]);
        if ($this->staffView() && (int) $ticket['user_id'] !== (int) Auth::id()) {
            Notify::send((int) $ticket['user_id'], 'Reply on ticket ' . $ticket['ticket_number'], $ticket['subject'], '/services/tickets/' . $id);
        }
        $this->success('Reply sent.', '/services/tickets/' . $id);
    }

    public function changeStatus(Request $request, string $id): never
    {
        $this->authorize('tickets.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['status' => 'required|in:open,in_progress,resolved,closed']);
        Database::statement('UPDATE support_tickets SET status = ?, resolved_at = IF(? = "resolved", ?, resolved_at), updated_at = ? WHERE id = ?',
            [$data['status'], $data['status'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), (int) $id]);
        $this->success('Status updated.', '/services/tickets/' . $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('tickets.delete');
        $this->verifyCsrf($request);
        $this->model->delete((int)$id);
        $this->success('Ticket deleted.', '/services/tickets');
    }
}
