<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class MedicalRecord extends Model
{
    protected string $table = 'medical_records';
    protected bool $timestamps = false;
    protected array $fillable = ['student_id', 'visit_date', 'complaint', 'diagnosis', 'treatment', 'attended_by', 'is_confidential'];
    protected array $searchable = ['complaint', 'diagnosis'];
}
