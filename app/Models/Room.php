<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Room extends Model
{
    protected string $table = 'rooms';
    protected bool $timestamps = false;
    protected array $fillable = ['building_id', 'code', 'name', 'floor', 'capacity', 'room_type', 'has_projector', 'status'];
    protected array $searchable = ['code', 'name'];
}
