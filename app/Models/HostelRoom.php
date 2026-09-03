<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class HostelRoom extends Model
{
    protected string $table = 'hostel_rooms';
    protected bool $timestamps = false;
    protected array $fillable = ['hostel_id', 'room_number', 'floor', 'room_type', 'capacity', 'occupied', 'fee_per_semester', 'status'];
    protected array $searchable = ['room_number'];
}
