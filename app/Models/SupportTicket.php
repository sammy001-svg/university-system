<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class SupportTicket extends Model
{
    protected string $table = 'support_tickets';
    protected array $fillable = ['ticket_number', 'user_id', 'category', 'subject', 'body', 'priority', 'status', 'assigned_to', 'resolved_at', 'resolution'];
    protected array $searchable = ['ticket_number', 'subject'];
}
