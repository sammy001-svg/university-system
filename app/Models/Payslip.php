<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Payslip extends Model
{
    protected string $table = 'payslips';
    protected bool $timestamps = false;
    protected array $fillable = ['payroll_period_id', 'staff_id', 'basic_salary', 'house_allowance', 'transport_allowance', 'other_allowances', 'gross_pay', 'paye', 'nssf', 'nhif', 'other_deductions', 'total_deductions', 'net_pay', 'status'];
    protected array $searchable = [];
}
