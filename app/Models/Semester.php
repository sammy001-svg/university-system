<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

final class Semester extends Model
{
    protected string $table = 'semesters';
    protected array $fillable = [
        'academic_year_id', 'name', 'semester_number', 'start_date', 'end_date',
        'registration_start', 'registration_end', 'exam_start', 'exam_end',
        'is_current', 'results_published', 'status',
    ];
    protected array $searchable = ['name'];

    public function current(): ?array
    {
        return Database::selectOne(
            'SELECT s.*, ay.name AS academic_year
               FROM semesters s
               JOIN academic_years ay ON ay.id = s.academic_year_id
              WHERE s.is_current = 1
              LIMIT 1'
        );
    }

    public function withYear(): array
    {
        return Database::select(
            'SELECT s.*, ay.name AS academic_year
               FROM semesters s
               JOIN academic_years ay ON ay.id = s.academic_year_id
              ORDER BY ay.start_date DESC, s.semester_number DESC'
        );
    }

    public function options(string $labelColumn = 'name', ?string $orderBy = null, array $where = []): array
    {
        $rows = Database::select(
            "SELECT s.id, CONCAT(ay.name, ' - ', s.name) AS label
               FROM semesters s
               JOIN academic_years ay ON ay.id = s.academic_year_id
              ORDER BY ay.start_date DESC, s.semester_number DESC"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['id']] = $row['label'];
        }
        return $out;
    }

    /** Exactly one semester may be flagged current. */
    public function makeCurrent(int $semesterId): void
    {
        Database::transaction(function () use ($semesterId) {
            $semester = $this->find($semesterId);
            if ($semester === null) {
                return;
            }
            Database::statement('UPDATE semesters SET is_current = 0');
            Database::statement('UPDATE semesters SET is_current = 1, status = ? WHERE id = ?', ['active', $semesterId]);
            Database::statement('UPDATE academic_years SET is_current = 0');
            Database::statement(
                'UPDATE academic_years SET is_current = 1, status = ? WHERE id = ?',
                ['active', $semester['academic_year_id']]
            );
        });
    }

    public function registrationOpen(?array $semester = null): bool
    {
        $semester ??= $this->current();
        if ($semester === null) {
            return false;
        }
        $today = date('Y-m-d');
        $from  = $semester['registration_start'] ?? null;
        $to    = $semester['registration_end'] ?? null;
        if ($from === null || $to === null) {
            return $semester['status'] === 'active';
        }
        return $today >= $from && $today <= $to;
    }
}
