<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Scholarship extends Model
{
    protected string $table = 'scholarships';
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'sponsor', 'award_type', 'percentage', 'amount', 'slots', 'criteria', 'status'];
    protected array $searchable = ['name', 'sponsor'];
}
