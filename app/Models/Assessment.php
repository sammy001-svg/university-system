<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Assessment extends Model
{
    protected string $table = 'assessments';
    protected array $fillable = ['offering_id', 'title', 'type', 'max_score', 'weight', 'due_date', 'instructions', 'attachment', 'is_published', 'created_by'];
    protected array $searchable = ['title'];
}
