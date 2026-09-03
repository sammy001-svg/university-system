<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AttendanceRecord extends Model
{
    protected string $table = 'attendance_records';
    protected bool $timestamps = false;
    protected array $fillable = ['session_id', 'student_id', 'status', 'remarks', 'marked_by'];
    protected array $searchable = [];
}
