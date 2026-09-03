<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Payment extends Model
{
    protected string $table = 'payments';
    protected bool $timestamps = false;
    protected array $fillable = [
        'receipt_number', 'student_id', 'invoice_id', 'amount', 'method', 'reference',
        'bank_name', 'paid_at', 'received_by', 'status', 'notes',
    ];
    protected array $searchable = ['p.receipt_number', 'p.reference', 's.admission_number', 'u.first_name', 'u.last_name'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('payments', 'p')
            ->select(
                'p.*', 's.admission_number', 'u.first_name', 'u.last_name',
                'i.invoice_number', 'prog.name AS program_name',
                'CONCAT(rec.first_name, " ", rec.last_name) AS received_by_name'
            )
            ->join('students s', 's.id = p.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('programs prog', 'prog.id = s.program_id')
            ->leftJoin('invoices i', 'i.id = p.invoice_id')
            ->leftJoin('users rec', 'rec.id = p.received_by');
    }

    public function nextReceiptNumber(): string
    {
        $prefix = 'RCP-' . date('Ym') . '-';
        $last   = Database::scalar(
            'SELECT receipt_number FROM payments WHERE receipt_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        );
        $next = $last === null ? 1 : ((int) substr((string) $last, -5)) + 1;
        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function collectedBetween(string $from, string $to): float
    {
        return (float) Database::scalar(
            'SELECT COALESCE(SUM(amount), 0) FROM payments
              WHERE status = "confirmed" AND DATE(paid_at) BETWEEN ? AND ?',
            [$from, $to]
        );
    }

    public function byMethod(string $from, string $to): array
    {
        return Database::select(
            'SELECT method, COUNT(*) AS transactions, COALESCE(SUM(amount), 0) AS total
               FROM payments
              WHERE status = "confirmed" AND DATE(paid_at) BETWEEN ? AND ?
              GROUP BY method ORDER BY total DESC',
            [$from, $to]
        );
    }

    /** Monthly collection totals for the dashboard chart. */
    public function monthlyTrend(int $months = 12): array
    {
        return Database::select(
            'SELECT DATE_FORMAT(paid_at, "%Y-%m") AS period, COALESCE(SUM(amount), 0) AS total
               FROM payments
              WHERE status = "confirmed" AND paid_at >= DATE_SUB(CURDATE(), INTERVAL ' . max(1, $months) . ' MONTH)
              GROUP BY period ORDER BY period'
        );
    }
}
