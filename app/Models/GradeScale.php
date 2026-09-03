<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class GradeScale extends Model
{
    protected string $table = 'grade_scales';
    protected bool $timestamps = false;
    protected array $fillable = ['grade', 'min_score', 'max_score', 'grade_point', 'remarks', 'is_pass'];
    protected array $searchable = ['grade'];
}
