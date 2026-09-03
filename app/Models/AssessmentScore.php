<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class AssessmentScore extends Model
{
    protected string $table = 'assessment_scores';
    protected bool $timestamps = false;
    protected array $fillable = ['assessment_id', 'student_id', 'score', 'submitted_at', 'graded_by', 'graded_at', 'feedback', 'status'];
    protected array $searchable = [];
}
