<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Payslip extends Model
{
    protected string $table = 'payslips';
    protected bool $timestamps = false;
    protected array $fillable = ['payroll_period_id', 'staff_id', 'basic_salary', 'allowances', 'gross_pay',
                                 'taxable_amount', 'tax_deduction', 'social_security', 'loan_deduction', 'penalty',
                                 'other_deductions', 'total_deductions', 'net_pay', 'outstanding_balance',
                                 'notes', 'status', 'paid_at'];
    protected array $searchable = [];
}
