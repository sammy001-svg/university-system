<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class SalaryComponent extends Model
{
    protected string $table = 'salary_components';
    protected bool $timestamps = false;
    protected array $fillable = ['code', 'name', 'kind', 'basis', 'amount', 'taxable', 'applies_to_all',
                                 'description', 'status', 'sort_order'];
    protected array $searchable = ['code', 'name'];
}
