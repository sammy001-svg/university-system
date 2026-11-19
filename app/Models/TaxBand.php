<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class TaxBand extends Model
{
    protected string $table = 'tax_bands';
    protected bool $timestamps = false;
    protected array $fillable = ['name', 'band_from', 'band_to', 'rate', 'fixed_amount', 'period',
                                 'effective_from', 'status', 'sort_order'];
    protected array $searchable = ['name'];
}
