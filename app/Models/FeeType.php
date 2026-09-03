<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class FeeType extends Model
{
    protected string $table = 'fee_types';
    protected bool $timestamps = false;
    protected array $fillable = ['code', 'name', 'category', 'is_recurring', 'is_mandatory', 'description', 'status'];
    protected array $searchable = ['code', 'name'];
}
