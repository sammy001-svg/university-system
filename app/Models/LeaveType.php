<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LeaveType extends Model
{
    protected string $table = 'leave_types';
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'days_allowed', 'is_paid', 'description'];
    protected array $searchable = ['name'];
}
