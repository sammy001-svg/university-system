<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Exam extends Model
{
    protected string $table = 'exams';
    protected bool $timestamps = false;
    protected array $fillable = ['offering_id', 'semester_id', 'exam_type', 'exam_date', 'start_time', 'duration_mins', 'room_id', 'invigilator_id', 'max_score', 'instructions', 'status'];
    protected array $searchable = [];
}
