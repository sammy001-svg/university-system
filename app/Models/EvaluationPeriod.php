<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class EvaluationPeriod extends Model
{
    protected string $table = 'evaluation_periods';
    protected bool $timestamps = false;
    protected array $fillable = ['semester_id', 'title', 'opens_at', 'closes_at', 'min_responses', 'status'];
    protected array $searchable = ['e.title'];
}
