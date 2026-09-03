<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class CourseResult extends Model
{
    protected string $table = 'course_results';
    protected array $fillable = [
        'student_id', 'offering_id', 'semester_id', 'course_id', 'coursework_score', 'exam_score',
        'total_score', 'grade', 'grade_point', 'credit_hours', 'quality_points', 'attempt',
        'outcome', 'is_published', 'published_at', 'entered_by', 'approved_by', 'remarks',
    ];

    private static ?array $scale = null;

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('course_results', 'res')
            ->select(
                'res.*', 's.admission_number', 'u.first_name', 'u.last_name',
                'c.code', 'c.title', 'o.section', 'sem.name AS semester_name', 'ay.name AS academic_year'
            )
            ->join('students s', 's.id = res.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('courses c', 'c.id = res.course_id')
            ->join('course_offerings o', 'o.id = res.offering_id')
            ->join('semesters sem', 'sem.id = res.semester_id')
            ->join('academic_years ay', 'ay.id = sem.academic_year_id');
    }

    /** The configured grading scale, ordered from highest band down. */
    public static function scale(): array
    {
        if (self::$scale === null) {
            self::$scale = Database::select('SELECT * FROM grade_scales ORDER BY min_score DESC');
        }
        return self::$scale;
    }

    /** Map a percentage to its grade band. */
    public static function gradeFor(float $score): array
    {
        foreach (self::scale() as $band) {
            if ($score >= (float) $band['min_score'] && $score <= (float) $band['max_score']) {
                return [
                    'grade'       => $band['grade'],
                    'grade_point' => (float) $band['grade_point'],
                    'is_pass'     => (bool) $band['is_pass'],
                    'remarks'     => $band['remarks'],
                ];
            }
        }
        return ['grade' => 'E', 'grade_point' => 0.0, 'is_pass' => false, 'remarks' => 'Fail'];
    }

    /**
     * Record or update one student's mark for an offering.
     * Coursework and exam are weighted per the offering configuration.
     */
    public function record(int $studentId, int $offeringId, float $coursework, float $exam, ?int $enteredBy = null): array
    {
        $offering = Database::selectOne(
            'SELECT o.*, c.credit_hours, c.id AS course_id FROM course_offerings o
               JOIN courses c ON c.id = o.course_id WHERE o.id = ?',
            [$offeringId]
        );
        if ($offering === null) {
            return ['ok' => false, 'message' => 'Class not found.'];
        }

        $cwWeight = (float) $offering['coursework_weight'];
        $exWeight = (float) $offering['exam_weight'];
        $total    = round((($coursework * $cwWeight) / 100) + (($exam * $exWeight) / 100), 2);
        $band     = self::gradeFor($total);
        $credits  = (int) $offering['credit_hours'];

        $existing = Database::selectOne(
            'SELECT id, attempt FROM course_results WHERE student_id = ? AND offering_id = ? ORDER BY attempt DESC LIMIT 1',
            [$studentId, $offeringId]
        );

        $payload = [
            'student_id'       => $studentId,
            'offering_id'      => $offeringId,
            'semester_id'      => (int) $offering['semester_id'],
            'course_id'        => (int) $offering['course_id'],
            'coursework_score' => round($coursework, 2),
            'exam_score'       => round($exam, 2),
            'total_score'      => $total,
            'grade'            => $band['grade'],
            'grade_point'      => $band['grade_point'],
            'credit_hours'     => $credits,
            'quality_points'   => round($band['grade_point'] * $credits, 2),
            'outcome'          => $band['is_pass'] ? 'pass' : 'fail',
            'entered_by'       => $enteredBy,
        ];

        if ($existing !== null) {
            $this->update((int) $existing['id'], $payload);
            $id = (int) $existing['id'];
        } else {
            $payload['attempt'] = 1;
            $id = $this->create($payload);
        }

        return ['ok' => true, 'id' => $id, 'total' => $total, 'grade' => $band['grade']];
    }

    /** Publish every result for an offering so students can see them. */
    public function publishOffering(int $offeringId, ?int $approvedBy = null): int
    {
        return Database::statement(
            'UPDATE course_results SET is_published = 1, published_at = NOW(), approved_by = ?
              WHERE offering_id = ? AND is_published = 0',
            [$approvedBy, $offeringId]
        );
    }

    public function unpublishOffering(int $offeringId): int
    {
        return Database::statement(
            'UPDATE course_results SET is_published = 0, published_at = NULL WHERE offering_id = ?',
            [$offeringId]
        );
    }

    public function forStudentSemester(int $studentId, int $semesterId, bool $publishedOnly = true): array
    {
        $sql = 'SELECT res.*, c.code, c.title
                  FROM course_results res
                  JOIN courses c ON c.id = res.course_id
                 WHERE res.student_id = ? AND res.semester_id = ?';
        if ($publishedOnly) {
            $sql .= ' AND res.is_published = 1';
        }
        $sql .= ' ORDER BY c.code';
        return Database::select($sql, [$studentId, $semesterId]);
    }

    /** Complete published transcript. */
    public function transcript(int $studentId): array
    {
        return Database::select(
            'SELECT res.*, c.code, c.title, sem.name AS semester_name, sem.semester_number,
                    ay.name AS academic_year, ay.start_date
               FROM course_results res
               JOIN courses c         ON c.id = res.course_id
               JOIN semesters sem     ON sem.id = res.semester_id
               JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE res.student_id = ? AND res.is_published = 1
              ORDER BY ay.start_date, sem.semester_number, c.code',
            [$studentId]
        );
    }
}
