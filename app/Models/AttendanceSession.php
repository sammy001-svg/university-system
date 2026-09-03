<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AttendanceSession extends Model
{
    protected string $table = 'attendance_sessions';
    protected bool $timestamps = false;
    protected array $fillable = ['offering_id', 'session_date', 'start_time', 'end_time', 'topic', 'session_type', 'taken_by', 'status'];
    protected array $searchable = ['topic'];
}
