<?php
declare(strict_types=1);

namespace App\Services\Academic;

use App\Core\Database;
use App\Models\FeeStructure;

/**
 * The figures behind the two printed records of the Office of Admission and Records:
 * the semester grade report ("student records") and the transcript of academic record.
 *
 * Grade points follow the rest of the system: a course's quality points are its grade
 * point times its credit hours, and a GPA is quality points divided by credit hours,
 * leaving audited courses out.
 */
final class AcademicRecord
{
    private const ORDINALS = [1 => 'FIRST', 2 => 'SECOND', 3 => 'THIRD', 4 => 'FOURTH'];

    /** The student, with every name the printed forms carry. */
    public static function student(int $studentId): ?array
    {
        return Database::selectOne(
            'SELECT s.*, u.first_name, u.last_name, u.other_name, u.gender, u.date_of_birth, u.email, u.phone,
                    p.name AS program_name, p.code AS program_code, p.level AS program_level, p.duration_years,
                    mp.name AS minor_name, d.name AS department_name, f.name AS faculty_name
               FROM students s
               JOIN users u        ON u.id = s.user_id
               JOIN programs p     ON p.id = s.program_id
               JOIN departments d  ON d.id = p.department_id
               JOIN faculties f    ON f.id = d.faculty_id
          LEFT JOIN programs mp    ON mp.id = s.minor_program_id
              WHERE s.id = ?',
            [$studentId]
        );
    }

    /** "Bettie, Blessing P." - the surname first, as the forms print it. */
    public static function formalName(array $student): string
    {
        $rest = trim($student['first_name'] . ' ' . (string) ($student['other_name'] ?? ''));
        return trim($student['last_name'] . ', ' . (string) preg_replace('/\s+/', ' ', $rest), ', ');
    }

    /** Semesters the student has published results for, newest first. */
    public static function semestersWithResults(int $studentId): array
    {
        return Database::select(
            'SELECT sem.id, CONCAT(ay.name, " ", sem.name) AS label
               FROM course_results r
               JOIN semesters sem     ON sem.id = r.semester_id
               JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE r.student_id = ? AND r.is_published = 1
              GROUP BY sem.id, ay.name, sem.name, ay.start_date, sem.semester_number
              ORDER BY ay.start_date DESC, sem.semester_number DESC',
            [$studentId]
        );
    }

    /**
     * One semester's grade report.
     * @return array{semester:array, rows:list<array>, credits:int, points:float, gpa:float, standing:string}|null
     */
    public static function semester(int $studentId, int $semesterId): ?array
    {
        $semester = Database::selectOne(
            'SELECT sem.*, ay.name AS academic_year FROM semesters sem JOIN academic_years ay ON ay.id = sem.academic_year_id WHERE sem.id = ?',
            [$semesterId]
        );
        if ($semester === null) {
            return null;
        }
        $rows = Database::select(
            'SELECT r.*, c.code, c.title FROM course_results r JOIN courses c ON c.id = r.course_id
              WHERE r.student_id = ? AND r.semester_id = ? AND r.is_published = 1
              ORDER BY c.code',
            [$studentId, $semesterId]
        );
        [$credits, $points] = self::totals($rows);

        // The standing is the one recorded for that semester, not the student's standing today.
        $year = Database::scalar('SELECT year_of_study FROM semester_results WHERE student_id = ? AND semester_id = ?', [$studentId, $semesterId])
            ?? Database::scalar('SELECT year_of_study FROM students WHERE id = ?', [$studentId]);

        return [
            'semester' => $semester,
            'rows'     => $rows,
            'credits'  => $credits,
            'points'   => $points,
            'gpa'      => self::gpa($points, $credits),
            'standing' => FeeStructure::classStanding(['year_of_study' => (int) $year, 'current_semester' => (int) $semester['semester_number']]),
        ];
    }

    /**
     * Every published result, by term, with each term's GPA and the cumulative GPA
     * at the end of each academic year.
     *
     * @return array{terms:list<array>, credits:int, points:float, cgpa:float, from:?string, to:?string}
     */
    public static function transcript(int $studentId): array
    {
        $rows = Database::select(
            'SELECT r.*, c.code, c.title, sem.id AS sem_id, sem.name AS semester_name, sem.semester_number,
                    sem.start_date, sem.end_date, ay.name AS academic_year, ay.id AS year_id, ay.start_date AS year_start
               FROM course_results r
               JOIN courses c         ON c.id = r.course_id
               JOIN semesters sem     ON sem.id = r.semester_id
               JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE r.student_id = ? AND r.is_published = 1
              ORDER BY ay.start_date, sem.semester_number, c.code',
            [$studentId]
        );

        $terms = [];
        foreach ($rows as $row) {
            $key = (int) $row['sem_id'];
            if (!isset($terms[$key])) {
                $terms[$key] = [
                    'label'   => (self::ORDINALS[(int) $row['semester_number']] ?? 'SEMESTER') . ' SEMESTER ' . $row['academic_year'],
                    'year_id' => (int) $row['year_id'],
                    'start'   => $row['start_date'],
                    'end'     => $row['end_date'],
                    'rows'    => [],
                ];
            }
            $terms[$key]['rows'][] = $row;
        }

        $cumCredits = 0;
        $cumPoints  = 0.0;
        $terms      = array_values($terms);
        foreach ($terms as $i => &$term) {
            [$credits, $points] = self::totals($term['rows']);
            $cumCredits += $credits;
            $cumPoints  += $points;
            $term['credits']     = $credits;
            $term['points']      = $points;
            $term['gpa']         = self::gpa($points, $credits);
            $term['cum_credits'] = $cumCredits;
            $term['cum_points']  = $cumPoints;
            $term['cgpa']        = self::gpa($cumPoints, $cumCredits);
            // The cumulative line closes each academic year, and the record.
            $term['year_end']    = !isset($terms[$i + 1]) || $terms[$i + 1]['year_id'] !== $term['year_id'];
        }
        unset($term);

        return [
            'terms'   => $terms,
            'credits' => $cumCredits,
            'points'  => $cumPoints,
            'cgpa'    => self::gpa($cumPoints, $cumCredits),
            'from'    => $terms[0]['start'] ?? null,
            'to'      => $terms === [] ? null : end($terms)['end'],
        ];
    }

    /** @return array{0:int,1:float} credit hours and quality points, audited courses aside */
    private static function totals(array $rows): array
    {
        $credits = 0;
        $points  = 0.0;
        foreach ($rows as $row) {
            if (($row['outcome'] ?? '') === 'audit') {
                continue;
            }
            $credits += (int) $row['credit_hours'];
            $points  += (float) $row['quality_points'];
        }
        return [$credits, $points];
    }

    private static function gpa(float $points, int $credits): float
    {
        return $credits > 0 ? round($points / $credits, 2) : 0.0;
    }
}
