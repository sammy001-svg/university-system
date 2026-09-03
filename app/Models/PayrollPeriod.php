<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PayrollPeriod extends Model
{
    protected string $table = 'payroll_periods';
    protected bool $timestamps = false;
    protected array $fillable = ['period_name', 'month', 'year', 'pay_date', 'status', 'processed_by'];
    protected array $searchable = ['period_name'];
}
