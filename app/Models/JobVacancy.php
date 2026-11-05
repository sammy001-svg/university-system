<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class JobVacancy extends Model
{
    protected string $table = 'job_vacancies';
    protected bool $timestamps = true;
    protected array $fillable = ['reference', 'title', 'department_id', 'staff_category', 'employment_type', 'positions', 'description', 'requirements', 'closing_date', 'status'];
    protected array $searchable = ['v.reference', 'v.title'];
}
