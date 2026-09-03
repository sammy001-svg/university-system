<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Department extends Model
{
    protected string $table = 'departments';
    protected array $fillable = ['faculty_id', 'code', 'name', 'hod_id', 'email', 'phone', 'description', 'status'];
    protected array $searchable = ['code', 'name'];
}
