<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ProgramCourse extends Model
{
    protected string $table = 'program_courses';
    protected bool $timestamps = false;
    protected array $fillable = ['program_id', 'course_id', 'year_of_study', 'semester_number', 'course_type'];
    protected array $searchable = [];
}
