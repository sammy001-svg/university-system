<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AcademicYear extends Model
{
    protected string $table = 'academic_years';
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'start_date', 'end_date', 'is_current', 'status'];
    protected array $searchable = ['name'];
}
