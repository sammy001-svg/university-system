<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class TimetableSlot extends Model
{
    protected string $table = 'timetable_slots';
    protected bool $timestamps = false;
    protected array $fillable = ['offering_id', 'semester_id', 'day_of_week', 'start_time', 'end_time', 'room_id', 'session_type'];
    protected array $searchable = [];
}
