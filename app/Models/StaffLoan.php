<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class StaffLoan extends Model
{
    protected string $table = 'staff_loans';
    protected array $fillable = ['staff_id', 'reference', 'kind', 'principal', 'monthly_deduction', 'balance',
                                 'issued_on', 'first_deduction_on', 'reason', 'status', 'approved_by'];
    protected array $searchable = ['reference', 'reason'];
}
