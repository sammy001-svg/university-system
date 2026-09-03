<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class TicketReply extends Model
{
    protected string $table = 'ticket_replies';
    protected bool $timestamps = false;
    protected array $fillable = ['ticket_id', 'user_id', 'body', 'is_internal'];
    protected array $searchable = [];
}
