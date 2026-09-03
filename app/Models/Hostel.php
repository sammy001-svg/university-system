<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Hostel extends Model
{
    protected string $table = 'hostels';
    protected bool $timestamps = false;
    protected array $fillable = ['campus_id', 'code', 'name', 'gender', 'warden_id', 'total_rooms', 'location', 'status'];
    protected array $searchable = ['code', 'name'];

    /** Dropdown list: "CODE - Name". */
    public function options(string $labelColumn = 'name', ?string $orderBy = null, array $where = []): array
    {
        $rows = \App\Core\Database::select("SELECT id, CONCAT(code, ' - ', name) AS label FROM hostels ORDER BY name");
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['id']] = $row['label'];
        }
        return $out;
    }
}
