<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Faculty extends Model
{
    protected string $table = 'faculties';
    protected array $fillable = ['campus_id', 'code', 'name', 'dean_id', 'email', 'phone', 'description', 'established_year', 'status'];
    protected array $searchable = ['code', 'name'];
}
