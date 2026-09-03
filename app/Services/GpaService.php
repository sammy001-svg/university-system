<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * GPA / CGPA computation and end-of-semester progression decisions.
 *
 * GPA = sum(grade_point * credit_hours) / sum(credit_hours)
 * Only published results count towards the official record.
 */
final class GpaService
{
    public const PROBATION_GPA = 2.00;

    /** Recompute and store the semester result row for one student. */
    public function computeSemester(int $studentId, int $semesterId): array
    {
        $results = Database::select(
            'SELECT credit_hours, grade_point, quality_points, outcome
               FROM course_results
              WHERE student_id = ? AND semester_id = ? AND is_published = 1 AND outcome != "audit"',
            [$studentId, $semesterId]
        );

        $creditsRegistered = 0;
        $creditsEarned     = 0;
        $qualityPoints     = 0.0;

        foreach ($results as $row) {
            $credits            = (int) $row['credit_hours'];
            $creditsRegistered += $credits;
            $qualityPoints     += (float) $row['quality_points'];
            if ($row['outcome'] === 'pass') {
                $creditsEarned += $credits;
            }
        }

        $gpa = $creditsRegistered > 0 ? round($qualityPoints / $creditsRegistered, 2) : 0.00;

        $student = Database::selectOne('SELECT year_of_study FROM students WHERE id = ?', [$studentId]);
        $cgpa    = $this->computeCgpa($studentId);

        $decision = $this->decisionFor($gpa, $results);

        $existing = Database::selectOne(
            'SELECT id FROM semester_results WHERE student_id = ? AND semester_id = ?',
            [$studentId, $semesterId]
        );

        $payload = [
            $studentId,
            $semesterId,
            (int) ($student['year_of_study'] ?? 1),
            $creditsRegistered,
            $creditsEarned,
            $qualityPoints,
            $gpa,
            $cgpa,
            classify_gpa($cgpa),
            $decision,
        ];

        if ($existing !== null) {
            Database::statement(
                'UPDATE semester_results
                    SET year_of_study = ?, credits_registered = ?, credits_earned = ?, quality_points = ?,
                        gpa = ?, cgpa = ?, classification = ?, decision = ?, generated_at = NOW()
                  WHERE id = ?',
                [
                    (int) ($student['year_of_study'] ?? 1), $creditsRegistered, $creditsEarned,
                    $qualityPoints, $gpa, $cgpa, classify_gpa($cgpa), $decision, $existing['id'],
                ]
            );
        } else {
            Database::statement(
                'INSERT INTO semester_results
                    (student_id, semester_id, year_of_study, credits_registered, credits_earned,
                     quality_points, gpa, cgpa, classification, decision)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                $payload
            );
        }

        // Keep the running totals on the student record in step.
        Database::statement(
            'UPDATE students SET cgpa = ?, credits_earned =
                (SELECT COALESCE(SUM(credit_hours), 0) FROM course_results
                  WHERE student_id = ? AND outcome = "pass" AND is_published = 1)
             WHERE id = ?',
            [$cgpa, $studentId, $studentId]
        );

        return [
            'gpa'                => $gpa,
            'cgpa'               => $cgpa,
            'credits_registered' => $creditsRegistered,
            'credits_earned'     => $creditsEarned,
            'decision'           => $decision,
        ];
    }

    /** Cumulative GPA over every published result. */
    public function computeCgpa(int $studentId): float
    {
        $row = Database::selectOne(
            'SELECT COALESCE(SUM(quality_points), 0) AS points,
                    COALESCE(SUM(credit_hours), 0)   AS credits
               FROM course_results
              WHERE student_id = ? AND is_published = 1 AND outcome != "audit"',
            [$studentId]
        );
        $credits = (float) ($row['credits'] ?? 0);
        return $credits > 0 ? round((float) $row['points'] / $credits, 2) : 0.00;
    }

    private function decisionFor(float $gpa, array $results): string
    {
        if ($results === []) {
            return 'pending';
        }
        $failed = 0;
        foreach ($results as $row) {
            if ($row['outcome'] === 'fail') {
                $failed++;
            }
        }
        if ($failed === 0 && $gpa >= self::PROBATION_GPA) {
            return 'proceed';
        }
        if ($failed > 0 && $failed <= 2) {
            return 'supplementary';
        }
        if ($gpa < self::PROBATION_GPA || $failed > 2) {
            return 'repeat';
        }
        return 'proceed';
    }

    /** Recompute an entire semester for every student that has results. */
    public function computeSemesterForAll(int $semesterId): int
    {
        $studentIds = array_column(
            Database::select(
                'SELECT DISTINCT student_id FROM course_results WHERE semester_id = ? AND is_published = 1',
                [$semesterId]
            ),
            'student_id'
        );
        foreach ($studentIds as $studentId) {
            $this->computeSemester((int) $studentId, (int) $semesterId);
        }
        return count($studentIds);
    }

    /** Ranked list of students by GPA for a semester. */
    public function ranking(int $semesterId, ?int $programId = null, int $limit = 50): array
    {
        $sql = 'SELECT sr.gpa, sr.cgpa, sr.credits_earned, s.admission_number,
                       u.first_name, u.last_name, p.name AS program_name
                  FROM semester_results sr
                  JOIN students s ON s.id = sr.student_id
                  JOIN users u    ON u.id = s.user_id
                  JOIN programs p ON p.id = s.program_id
                 WHERE sr.semester_id = ?';
        $params = [$semesterId];
        if ($programId !== null) {
            $sql     .= ' AND s.program_id = ?';
            $params[] = $programId;
        }
        $sql .= ' ORDER BY sr.gpa DESC, sr.credits_earned DESC LIMIT ' . max(1, $limit);
        return Database::select($sql, $params);
    }
}
