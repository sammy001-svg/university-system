<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\QueryBuilder;

final class CourseRegistration extends Model
{
    protected string $table = 'course_registrations';
    protected array $fillable = [
        'student_id', 'offering_id', 'semester_id', 'registration_type',
        'approval_status', 'approved_by', 'approved_at', 'status', 'remarks',
    ];

    public function listing(): QueryBuilder
    {
        return QueryBuilder::table('course_registrations', 'cr')
            ->select(
                'cr.*', 's.admission_number', 'u.first_name', 'u.last_name',
                'c.code', 'c.title', 'c.credit_hours', 'o.section',
                'sem.name AS semester_name', 'p.name AS program_name'
            )
            ->join('students s', 's.id = cr.student_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('programs p', 'p.id = s.program_id')
            ->join('course_offerings o', 'o.id = cr.offering_id')
            ->join('courses c', 'c.id = o.course_id')
            ->join('semesters sem', 'sem.id = cr.semester_id');
    }

    public function isRegistered(int $studentId, int $offeringId): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM course_registrations WHERE student_id = ? AND offering_id = ?',
            [$studentId, $offeringId]
        ) > 0;
    }

    public function creditsFor(int $studentId, int $semesterId): int
    {
        return (int) Database::scalar(
            'SELECT COALESCE(SUM(c.credit_hours), 0)
               FROM course_registrations cr
               JOIN course_offerings o ON o.id = cr.offering_id
               JOIN courses c          ON c.id = o.course_id
              WHERE cr.student_id = ? AND cr.semester_id = ? AND cr.status = "registered"',
            [$studentId, $semesterId]
        );
    }

    /**
     * Register a student for an offering, enforcing capacity, duplicates and prerequisites.
     * @return array{ok:bool,message:string}
     */
    public function register(int $studentId, int $offeringId, int $semesterId, string $type = 'normal'): array
    {
        if ($this->isRegistered($studentId, $offeringId)) {
            return ['ok' => false, 'message' => 'You are already registered for this class.'];
        }

        $offering = Database::selectOne(
            'SELECT o.*, c.code, c.title, c.id AS course_id FROM course_offerings o
               JOIN courses c ON c.id = o.course_id WHERE o.id = ?',
            [$offeringId]
        );
        if ($offering === null) {
            return ['ok' => false, 'message' => 'That class could not be found.'];
        }
        if ($offering['status'] !== 'open') {
            return ['ok' => false, 'message' => 'Registration for this class is closed.'];
        }

        $taken = (int) Database::scalar(
            'SELECT COUNT(*) FROM course_registrations WHERE offering_id = ? AND status = "registered"',
            [$offeringId]
        );
        if ($taken >= (int) $offering['capacity']) {
            return ['ok' => false, 'message' => 'This class is already full.'];
        }

        $missing = $this->unmetPrerequisites($studentId, (int) $offering['course_id']);
        if ($missing !== []) {
            return [
                'ok'      => false,
                'message' => 'Prerequisite not met: ' . implode(', ', $missing) . '.',
            ];
        }

        Database::transaction(function () use ($studentId, $offeringId, $semesterId, $type) {
            $this->create([
                'student_id'        => $studentId,
                'offering_id'       => $offeringId,
                'semester_id'       => $semesterId,
                'registration_type' => $type,
                'approval_status'   => 'pending',
                'status'            => 'registered',
            ]);
            Database::statement(
                'UPDATE course_offerings SET enrolled_count = enrolled_count + 1 WHERE id = ?',
                [$offeringId]
            );
        });

        return ['ok' => true, 'message' => $offering['code'] . ' registered successfully.'];
    }

    /** Prerequisite courses the student has not yet passed. */
    public function unmetPrerequisites(int $studentId, int $courseId): array
    {
        $rows = Database::select(
            'SELECT pre.id, pre.code, pre.title
               FROM course_prerequisites cp
               JOIN courses pre ON pre.id = cp.prerequisite_course_id
              WHERE cp.course_id = ?',
            [$courseId]
        );
        $missing = [];
        foreach ($rows as $row) {
            $passed = (int) Database::scalar(
                'SELECT COUNT(*) FROM course_results
                  WHERE student_id = ? AND course_id = ? AND outcome = "pass"',
                [$studentId, $row['id']]
            );
            if ($passed === 0) {
                $missing[] = $row['code'];
            }
        }
        return $missing;
    }

    public function drop(int $registrationId): bool
    {
        $registration = $this->find($registrationId);
        if ($registration === null) {
            return false;
        }
        Database::transaction(function () use ($registration, $registrationId) {
            $this->update($registrationId, ['status' => 'dropped']);
            Database::statement(
                'UPDATE course_offerings SET enrolled_count = GREATEST(enrolled_count - 1, 0) WHERE id = ?',
                [$registration['offering_id']]
            );
        });
        return true;
    }
}
