<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Graduation extends Model
{
    protected string $table = 'graduations';
    protected bool $timestamps = false;
    protected array $fillable = ['student_id', 'academic_year_id', 'graduation_date', 'final_cgpa', 'classification', 'certificate_no', 'status', 'remarks'];
    protected array $searchable = ['certificate_no'];
}
