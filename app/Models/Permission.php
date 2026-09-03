<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Permission extends Model
{
    protected string $table = 'permissions';
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'slug', 'module', 'description'];
    protected array $searchable = ['name', 'slug', 'module'];

    public function modules(): array
    {
        return array_column(
            Database::select('SELECT DISTINCT module FROM permissions ORDER BY module'),
            'module'
        );
    }
}
