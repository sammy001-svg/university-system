<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AppraisalCycle extends Model
{
    protected string $table = 'appraisal_cycles';
    protected bool $timestamps = false;
    protected array $fillable = ['title', 'period_start', 'period_end', 'due_date', 'status'];
    protected array $searchable = ['title'];
}
