<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class FeeStructure extends Model
{
    protected string $table = 'fee_structures';
    protected array $fillable = [
        'program_id', 'academic_year_id', 'year_of_study', 'semester_number',
        'study_mode', 'name', 'total_amount', 'status',
    ];
    protected array $searchable = ['fs.name'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('fee_structures', 'fs')
            ->select(
                'fs.*', 'p.name AS program_name', 'p.code AS program_code', 'ay.name AS academic_year',
                '(SELECT COUNT(*) FROM fee_structure_items i WHERE i.fee_structure_id = fs.id) AS items_count'
            )
            ->join('programs p', 'p.id = fs.program_id')
            ->join('academic_years ay', 'ay.id = fs.academic_year_id');
    }

    public function items(int $structureId): array
    {
        return Database::select(
            'SELECT i.*, ft.name AS fee_type_name, ft.code AS fee_type_code, ft.category
               FROM fee_structure_items i
               JOIN fee_types ft ON ft.id = i.fee_type_id
              WHERE i.fee_structure_id = ?
              ORDER BY ft.category, ft.name',
            [$structureId]
        );
    }

    public function recalculateTotal(int $structureId): void
    {
        Database::statement(
            'UPDATE fee_structures SET total_amount =
                (SELECT COALESCE(SUM(amount), 0) FROM fee_structure_items WHERE fee_structure_id = ?)
             WHERE id = ?',
            [$structureId, $structureId]
        );
    }

    /** Find the structure that applies to a student for a given year. */
    public function resolveFor(int $programId, int $academicYearId, int $yearOfStudy, int $semesterNumber, string $mode = 'full_time'): ?array
    {
        return Database::selectOne(
            'SELECT * FROM fee_structures
              WHERE program_id = ? AND academic_year_id = ? AND year_of_study = ?
                AND semester_number = ? AND study_mode = ? AND status = "active"
              LIMIT 1',
            [$programId, $academicYearId, $yearOfStudy, $semesterNumber, $mode]
        );
    }
}
