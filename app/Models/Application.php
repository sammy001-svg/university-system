<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class Application extends Model
{
    protected string $table = 'applications';
    protected array $fillable = [
        'application_number', 'user_id', 'intake_id', 'program_id', 'alt_program_id',
        'first_name', 'last_name', 'other_name', 'email', 'phone', 'gender', 'date_of_birth',
        'nationality', 'national_id', 'address', 'county', 'previous_school', 'qualification',
        'grade_obtained', 'year_completed', 'study_mode', 'sponsor_type', 'personal_statement',
        'application_fee_paid', 'score', 'status', 'reviewed_by', 'reviewed_at', 'remarks', 'submitted_at',
    ];
    protected array $searchable = ['a.application_number', 'a.first_name', 'a.last_name', 'a.email', 'a.phone'];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('applications', 'a')
            ->select(
                'a.*', 'p.name AS program_name', 'p.code AS program_code',
                'i.name AS intake_name', 'ay.name AS academic_year',
                'CONCAT(r.first_name, " ", r.last_name) AS reviewer_name'
            )
            ->join('programs p', 'p.id = a.program_id')
            ->join('intakes i', 'i.id = a.intake_id')
            ->join('academic_years ay', 'ay.id = i.academic_year_id')
            ->leftJoin('users r', 'r.id = a.reviewed_by');
    }

    public function detail(int $id): ?array
    {
        return $this->listing()->where('a.id', $id)->first();
    }

    public function nextApplicationNumber(): string
    {
        $prefix = 'APP-' . date('Y') . '-';
        $last   = Database::scalar(
            'SELECT application_number FROM applications WHERE application_number LIKE ? ORDER BY id DESC LIMIT 1',
            [$prefix . '%']
        );
        $next = $last === null ? 1 : ((int) substr((string) $last, -5)) + 1;
        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function documents(int $applicationId): array
    {
        return Database::select(
            'SELECT * FROM application_documents WHERE application_id = ? ORDER BY uploaded_at DESC',
            [$applicationId]
        );
    }

    public function countByStatus(?int $intakeId = null): array
    {
        $sql    = 'SELECT status, COUNT(*) AS total FROM applications';
        $params = [];
        if ($intakeId !== null) {
            $sql     .= ' WHERE intake_id = ?';
            $params[] = $intakeId;
        }
        $sql .= ' GROUP BY status';

        $out = [];
        foreach (Database::select($sql, $params) as $row) {
            $out[$row['status']] = (int) $row['total'];
        }
        return $out;
    }

    /** Applications per program, for the admissions dashboard. */
    public function byProgram(?int $intakeId = null): array
    {
        $sql = 'SELECT p.code, p.name, COUNT(a.id) AS total,
                       SUM(CASE WHEN a.status = "accepted" THEN 1 ELSE 0 END) AS accepted,
                       SUM(CASE WHEN a.status = "enrolled" THEN 1 ELSE 0 END) AS enrolled
                  FROM applications a JOIN programs p ON p.id = a.program_id';
        $params = [];
        if ($intakeId !== null) {
            $sql     .= ' WHERE a.intake_id = ?';
            $params[] = $intakeId;
        }
        $sql .= ' GROUP BY p.id ORDER BY total DESC';
        return Database::select($sql, $params);
    }
}
