<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;
use App\Core\Setting;

/**
 * A fee structure is a bill template. It can target the whole institution, a
 * college (faculty), a department or a single programme, and optionally only
 * new or only continuing students. When several match a student, the most
 * specific scope wins (programme > department > college > institution); within
 * the same scope a status-specific bill beats an "all students" bill.
 */
final class FeeStructure extends Model
{
    public const STATUSES = ['all' => 'All Students', 'new' => 'New Students', 'continuing' => 'Continuing Students'];

    protected string $table = 'fee_structures';
    protected array $fillable = [
        'faculty_id', 'department_id', 'program_id', 'academic_year_id', 'student_status', 'year_of_study',
        'semester_number', 'study_mode', 'name', 'total_amount', 'status',
    ];
    protected array $searchable = ['fs.name'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('fee_structures', 'fs')
            ->select(
                'fs.*', 'p.name AS program_name', 'p.code AS program_code', 'd.name AS department_name',
                'f.name AS faculty_name', 'ay.name AS academic_year',
                '(SELECT COUNT(*) FROM fee_structure_items i WHERE i.fee_structure_id = fs.id) AS items_count'
            )
            ->leftJoin('programs p', 'p.id = fs.program_id')
            ->leftJoin('departments d', 'd.id = fs.department_id')
            ->leftJoin('faculties f', 'f.id = fs.faculty_id')
            ->join('academic_years ay', 'ay.id = fs.academic_year_id');
    }

    /** Human label for who a structure bills, e.g. "College: Faculty of Health Sciences". */
    public static function scopeLabel(array $structure): string
    {
        return match (true) {
            !empty($structure['program_id'])    => 'Programme: ' . ($structure['program_name'] ?? '#' . $structure['program_id']),
            !empty($structure['department_id']) => 'Department: ' . ($structure['department_name'] ?? '#' . $structure['department_id']),
            !empty($structure['faculty_id'])    => 'College: ' . ($structure['faculty_name'] ?? '#' . $structure['faculty_id']),
            default                             => 'All Colleges',
        };
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

    /**
     * The installment plan for a structure. Without a saved plan the 1st
     * installment takes the fee_threshold_percent share of tuition and a 2nd
     * installment takes the rest.
     * @return list<array{installment_number:int,label:string,tuition_percent:float,due_days:int}>
     */
    public function installments(int $structureId): array
    {
        $rows = Database::select(
            'SELECT * FROM fee_structure_installments WHERE fee_structure_id = ? ORDER BY installment_number',
            [$structureId]
        );
        if ($rows !== []) {
            return array_map(static fn (array $r): array => [
                'id'                 => (int) $r['id'],
                'installment_number' => (int) $r['installment_number'],
                'label'              => (string) $r['label'],
                'tuition_percent'    => (float) $r['tuition_percent'],
                'due_days'           => (int) $r['due_days'],
            ], $rows);
        }

        $first = max(0.0, min(100.0, (float) Setting::get('fee_threshold_percent', 60)));
        $plan  = [['installment_number' => 1, 'label' => '1st Installment', 'tuition_percent' => $first, 'due_days' => 0]];
        if ($first < 100) {
            $plan[] = ['installment_number' => 2, 'label' => '2nd Installment', 'tuition_percent' => 100 - $first, 'due_days' => 56];
        }
        return $plan;
    }

    /**
     * Split a bill into installments. Every non-tuition fee is due with the 1st
     * installment; tuition is shared out by each installment's percentage and
     * the last installment absorbs rounding. A discount comes off tuition first.
     * @return list<array{installment_number:int,label:string,amount:float,due_days:int}>
     */
    public static function splitIntoInstallments(array $plan, float $tuition, float $otherFees, float $discount): array
    {
        $tuitionNet = max(0.0, $tuition - $discount);
        $otherNet   = max(0.0, $otherFees - max(0.0, $discount - $tuition));

        $result    = [];
        $allocated = 0.0;
        $last      = count($plan) - 1;
        foreach (array_values($plan) as $i => $step) {
            $share = $i === $last
                ? round($tuitionNet - $allocated, 2)
                : round($tuitionNet * $step['tuition_percent'] / 100, 2);
            $allocated += $share;
            $result[] = [
                'installment_number' => (int) $step['installment_number'],
                'label'              => (string) $step['label'],
                'amount'             => round($share + ($i === 0 ? $otherNet : 0.0), 2),
                'due_days'           => (int) $step['due_days'],
            ];
        }
        return $result;
    }

    /** New = in Year 1 and never billed for an earlier semester. */
    public function studentStatus(array $student, int $semesterId): string
    {
        if ((int) $student['year_of_study'] !== 1) {
            return 'continuing';
        }
        $earlier = Database::scalar(
            'SELECT COUNT(*) FROM invoices i
               JOIN semesters billed ON billed.id = i.semester_id
               JOIN semesters target ON target.id = ?
              WHERE i.student_id = ? AND i.status != "cancelled" AND billed.start_date < target.start_date',
            [$semesterId, (int) $student['id']]
        );
        return (int) $earlier > 0 ? 'continuing' : 'new';
    }

    /**
     * Find the structure that applies to a student for a semester.
     * $student needs id, program_id, department_id, faculty_id, year_of_study and study_mode.
     */
    public function resolveFor(array $student, array $semester): ?array
    {
        return Database::selectOne(
            'SELECT * FROM fee_structures
              WHERE academic_year_id = ? AND status = "active"
                AND (program_id IS NULL OR program_id = ?)
                AND (department_id IS NULL OR department_id = ?)
                AND (faculty_id IS NULL OR faculty_id = ?)
                AND student_status IN ("all", ?)
                AND (year_of_study IS NULL OR year_of_study = ?)
                AND (semester_number IS NULL OR semester_number = ?)
                AND (study_mode IS NULL OR study_mode = ?)
              ORDER BY program_id IS NOT NULL DESC, department_id IS NOT NULL DESC, faculty_id IS NOT NULL DESC,
                       student_status != "all" DESC, year_of_study IS NOT NULL DESC,
                       semester_number IS NOT NULL DESC, study_mode IS NOT NULL DESC, id DESC
              LIMIT 1',
            [
                (int) $semester['academic_year_id'],
                (int) $student['program_id'],
                (int) $student['department_id'],
                (int) $student['faculty_id'],
                $this->studentStatus($student, (int) $semester['id']),
                (int) $student['year_of_study'],
                (int) $semester['semester_number'],
                (string) $student['study_mode'],
            ]
        );
    }
}
