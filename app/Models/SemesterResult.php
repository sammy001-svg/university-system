<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class SemesterResult extends Model
{
    protected string $table = 'semester_results';
    protected bool $timestamps = false;
    protected array $fillable = ['student_id', 'semester_id', 'year_of_study', 'credits_registered', 'credits_earned', 'quality_points', 'gpa', 'cgpa', 'classification', 'decision', 'remarks'];
    protected array $searchable = [];
}
