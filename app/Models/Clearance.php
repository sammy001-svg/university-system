<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Clearance extends Model
{
    protected string $table = 'clearances';
    protected bool $timestamps = false;
    protected array $fillable = ['student_id', 'clearance_type', 'unit', 'status', 'cleared_by', 'cleared_at', 'remarks'];
    protected array $searchable = [];
}
