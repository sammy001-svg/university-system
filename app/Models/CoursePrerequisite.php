<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CoursePrerequisite extends Model
{
    protected string $table = 'course_prerequisites';
    protected bool $timestamps = false;
    protected array $fillable = ['course_id', 'prerequisite_course_id'];
    protected array $searchable = [];
}
