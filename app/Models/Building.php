<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Building extends Model
{
    protected string $table = 'buildings';
    protected bool $timestamps = false;
    protected array $fillable = ['campus_id', 'code', 'name', 'floors', 'status'];
    protected array $searchable = ['code', 'name'];
}
