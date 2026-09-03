<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Event extends Model
{
    protected string $table = 'events';
    protected bool $timestamps = false;
    protected array $fillable = ['title', 'description', 'event_type', 'start_datetime', 'end_datetime', 'venue', 'organizer', 'is_public', 'created_by'];
    protected array $searchable = ['title', 'venue'];
}
