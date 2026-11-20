<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** A product sold at the university store (to students, staff or walk-in customers). */
final class StoreProduct extends Model
{
    protected string $table = 'store_products';
    protected bool $timestamps = true;
    protected array $fillable = ['sku', 'name', 'category', 'description', 'unit', 'price', 'cost_price', 'quantity', 'reorder_level', 'is_active'];
    protected array $searchable = ['sku', 'name', 'category'];
}

