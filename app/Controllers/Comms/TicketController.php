<?php
declare(strict_types=1);

namespace App\Controllers\Comms;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\SupportTicket;

final class TicketController extends Controller
{
    private SupportTicket $model;
    public function __construct() { $this->model = new SupportTicket(); }

    public function index(Request $request): string
    {
        $this->authorize('tickets.view');
        $tickets = Database::select(
            'SELECT t.*, u.first_name, u.last_name FROM support_tickets t JOIN users u ON u.id=t.created_by ORDER BY t.created_at DESC LIMIT 100'
        );
        return $this->view('comms.tickets.index', ['pageTitle' => 'Support Tickets', 'tickets' => $tickets]);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('tickets.view');
        $ticket = Database::selectOne('SELECT t.*, u.first_name, u.last_name FROM support_tickets t JOIN users u ON u.id=t.created_by WHERE t.id=?', [(int)$id]);
        if (!$ticket) { throw new HttpException(404); }
        $replies = Database::select('SELECT r.*, u.first_name, u.last_name FROM ticket_replies r JOIN users u ON u.id=r.user_id WHERE r.ticket_id=? ORDER BY r.created_at', [(int)$id]);
        return $this->view('comms.tickets.show', ['pageTitle' => 'Ticket #' . $id, 'ticket' => $ticket, 'replies' => $replies]);
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
            'category' => 'required|in:technical,academic,finance,hostel,library,other',
            'priority' => 'required|in:low,normal,high,urgent',
        ]);
        $data['created_by']     = Auth::id();
        $data['status']         = 'open';
        $data['ticket_number']  = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $id = $this->model->create($data);
        $this->success('Ticket submitted. Ref: ' . $data['ticket_number'], '/services/tickets/' . $id);
    }

    public function reply(Request $request, string $id): never
    {
        $this->authorize('tickets.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['body' => 'required']);
        Database::statement('INSERT INTO ticket_replies (ticket_id, user_id, body) VALUES (?,?,?)', [(int)$id, Auth::id(), $data['body']]);
        Database::statement("UPDATE support_tickets SET status='in_progress', updated_at=NOW() WHERE id=?", [(int)$id]);
        $this->success('Reply sent.', '/services/tickets/' . $id);
    }

    public function changeStatus(Request $request, string $id): never
    {
        $this->authorize('tickets.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['status' => 'required|in:open,in_progress,resolved,closed']);
        Database::statement('UPDATE support_tickets SET status=?, updated_at=NOW() WHERE id=?', [$data['status'], (int)$id]);
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
