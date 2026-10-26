<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** A tuition band: the amount charged per credit hour for the courses assigned to it. */
final class TuitionRate extends Model
{
    protected string $table = 'tuition_rates';
    protected bool $timestamps = false;
    protected array $fillable = ['code', 'name', 'rate_per_credit', 'description', 'status'];
    protected array $searchable = ['code', 'name'];

    /** code => "Name ($x / credit)" for course forms. */
    public function choices(): array
    {
        $out = [];
        foreach (Database::select('SELECT code, name, rate_per_credit FROM tuition_rates WHERE status = "active" ORDER BY rate_per_credit') as $row) {
            $out[$row['code']] = $row['name'] . ' (' . money($row['rate_per_credit']) . ' / credit)';
        }
        return $out;
    }
}
