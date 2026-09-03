<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Intake extends Model
{
    protected string $table = 'intakes';
    protected bool $timestamps = false;
    protected array $fillable = ['academic_year_id', 'name', 'code', 'start_date', 'application_open', 'application_close', 'status'];
    protected array $searchable = ['name', 'code'];
}
