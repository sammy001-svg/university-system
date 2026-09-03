<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class StudentScholarship extends Model
{
    protected string $table = 'student_scholarships';
    protected bool $timestamps = false;
    protected array $fillable = ['student_id', 'scholarship_id', 'academic_year_id', 'amount', 'awarded_at', 'status', 'remarks'];
    protected array $searchable = [];
}
