<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LeaveRequest extends Model
{
    protected string $table = 'leave_requests';
    protected bool $timestamps = false;
    protected array $fillable = ['staff_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'reason', 'handover_to', 'status', 'approved_by', 'approved_at', 'remarks'];
    protected array $searchable = [];
}
