<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Asset extends Model
{
    protected string $table = 'assets';
    protected bool $timestamps = true;
    protected array $fillable = ['tag', 'name', 'category', 'serial_number', 'room_id', 'department_id', 'custodian_id', 'purchase_date', 'purchase_cost', 'supplier', 'warranty_until', 'asset_condition', 'status', 'notes'];
    protected array $searchable = ['a.tag', 'a.name', 'a.serial_number', 'a.supplier'];
}
