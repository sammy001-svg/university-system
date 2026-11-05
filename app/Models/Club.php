<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Club extends Model
{
    protected string $table = 'clubs';
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'category', 'description', 'patron_id', 'meeting_schedule', 'open_to_join', 'status'];
    protected array $searchable = ['c.name', 'c.description'];
}
