<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class HostelAllocation extends Model
{
    protected string $table = 'hostel_allocations';
    protected bool $timestamps = false;
    protected array $fillable = ['student_id', 'hostel_room_id', 'semester_id', 'bed_number', 'allocated_at', 'vacated_at', 'allocated_by', 'status'];
    protected array $searchable = [];
}
