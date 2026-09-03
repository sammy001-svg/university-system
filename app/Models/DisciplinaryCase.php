<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class DisciplinaryCase extends Model
{
    protected string $table = 'disciplinary_cases';
    protected bool $timestamps = false;
    protected array $fillable = ['case_number', 'student_id', 'offence', 'description', 'incident_date', 'reported_by', 'hearing_date', 'verdict', 'penalty', 'fine_amount', 'status'];
    protected array $searchable = ['case_number', 'offence'];
}
