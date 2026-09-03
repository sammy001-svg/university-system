<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Campus extends Model
{
    protected string $table = 'campuses';
    protected bool $timestamps = false;
    protected array $fillable = ['code', 'name', 'address', 'city', 'country', 'phone', 'email', 'is_main', 'status'];
    protected array $searchable = ['code', 'name', 'city'];
}
