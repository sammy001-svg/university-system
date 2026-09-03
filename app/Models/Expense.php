<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Expense extends Model
{
    protected string $table = 'expenses';
    protected bool $timestamps = false;
    protected array $fillable = ['voucher_number', 'category', 'department_id', 'description', 'amount', 'payee', 'expense_date', 'payment_method', 'reference', 'approved_by', 'status', 'created_by'];
    protected array $searchable = ['voucher_number', 'description', 'payee'];
}
