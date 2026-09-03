<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class FeeStructureItem extends Model
{
    protected string $table = 'fee_structure_items';
    protected bool $timestamps = false;
    protected array $fillable = ['fee_structure_id', 'fee_type_id', 'amount', 'is_mandatory'];
    protected array $searchable = [];
}
