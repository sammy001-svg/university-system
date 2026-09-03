<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Guardian extends Model
{
    protected string $table = 'guardians';
    protected bool $timestamps = false;
    protected array $fillable = ['student_id', 'user_id', 'full_name', 'relationship', 'phone', 'email', 'occupation', 'address', 'is_primary'];
    protected array $searchable = ['full_name', 'phone', 'email'];
}
