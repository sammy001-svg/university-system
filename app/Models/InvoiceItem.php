<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class InvoiceItem extends Model
{
    protected string $table = 'invoice_items';
    protected bool $timestamps = false;
    protected array $fillable = ['invoice_id', 'fee_type_id', 'description', 'quantity', 'unit_amount', 'amount'];
    protected array $searchable = [];
}
