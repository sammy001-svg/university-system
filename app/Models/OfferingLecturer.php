<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class OfferingLecturer extends Model
{
    protected string $table = 'offering_lecturers';
    protected bool $timestamps = false;
    protected array $fillable = ['offering_id', 'staff_id', 'role'];
    protected array $searchable = [];
}
