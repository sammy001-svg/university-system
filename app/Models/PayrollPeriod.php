<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PayrollPeriod extends Model
{
    protected string $table = 'payroll_periods';
    protected bool $timestamps = false;
    protected array $fillable = ['period_name', 'month', 'year', 'pay_date', 'notes', 'status',
                                 'processed_by', 'approved_by', 'approved_at', 'paid_at'];
    protected array $searchable = ['period_name'];
}
