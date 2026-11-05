<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class StockItem extends Model
{
    protected string $table = 'stock_items';
    protected bool $timestamps = true;
    protected array $fillable = ['code', 'name', 'category', 'unit', 'quantity', 'reorder_level', 'unit_cost', 'location'];
    protected array $searchable = ['code', 'name', 'category'];
}
