<?php
declare(strict_types=1);

namespace App\Services\Elearning;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Exceptions\HttpException;

/**
 * Who may use a class offering's online classroom and examinations:
 * its lecturers (or anyone holding exams.edit, e.g. the registrar) teach it,
 * and the students registered on it take it.
 */
final class CourseAccess
{
    /** The signed-in user's staff id, or null. */
    public static function staffId(): ?int
    {
        $id = Database::scalar('SELECT id FROM staff WHERE user_id = ?', [Auth::id()]);
        return $id === null ? null : (int) $id;
    }

    /** The offering with course and semester details, if the signed-in user may teach it; 403/404 otherwise. */
    public static function teaching(int $offeringId): array
    {
        $offering = self::offering($offeringId);
        if ($offering === null) {
            throw new HttpException(404, 'Class not found.');
        }
        if (!Auth::can('exams.edit')) {
            $staffId = self::staffId();
            $teaches = $staffId !== null && ((int) $offering['lecturer_id'] === $staffId || Database::scalar(
                'SELECT 1 FROM offering_lecturers WHERE offering_id = ? AND staff_id = ?',
                [$offeringId, $staffId]
            ));
            if (!$teaches) {
                throw new HttpException(403, 'You do not teach this class.');
            }
        }
        return $offering;
    }

    /** May the signed-in user teach the offering? (Same rule as teaching(), without throwing.) */
    public static function mayTeach(int $offeringId): bool
    {
        if (!Auth::can('teaching.access') && !Auth::can('exams.edit')) {
            return false;
        }
        try {
            self::teaching($offeringId);
            return true;
        } catch (HttpException) {
            return false;
        }
    }

    /** The signed-in user's student id, when they are a student registered on the offering. */
    public static function studentOn(int $offeringId): ?int
    {
        $id = Database::scalar(
            'SELECT s.id FROM students s JOIN course_registrations cr ON cr.student_id = s.id AND cr.offering_id = ? AND cr.status <> "dropped"
              WHERE s.user_id = ?',
            [$offeringId, Auth::id()]
        );
        return $id === null ? null : (int) $id;
    }

    /** The offering, if the student is registered on it; 404 otherwise. */
    public static function studying(int $offeringId, int $studentId): array
    {
        $offering = self::offering($offeringId);
        $registered = $offering !== null && Database::scalar(
            'SELECT 1 FROM course_registrations WHERE offering_id = ? AND student_id = ? AND status <> "dropped"',
            [$offeringId, $studentId]
        );
        if (!$registered) {
            throw new HttpException(404, 'You are not registered for this course.');
        }
        return $offering;
    }

    public static function offering(int $offeringId): ?array
    {
        return Database::selectOne(
            'SELECT o.*, c.code, c.title, c.credit_hours, c.description AS course_description,
                    sem.name AS semester_name, ay.name AS academic_year,
                    CONCAT_WS(" ", lu.title, lu.first_name, lu.last_name) AS lecturer_name, lu.email AS lecturer_email
               FROM course_offerings o
               JOIN courses c         ON c.id = o.course_id
               JOIN semesters sem     ON sem.id = o.semester_id
               JOIN academic_years ay ON ay.id = sem.academic_year_id
          LEFT JOIN staff ls          ON ls.id = o.lecturer_id
          LEFT JOIN users lu          ON lu.id = ls.user_id
              WHERE o.id = ?',
            [$offeringId]
        );
    }

    /** Offering ids the signed-in lecturer teaches (all offerings for exams.edit holders is not implied here). */
    public static function taughtOfferingIds(int $staffId): array
    {
        return array_map('intval', array_column(Database::select(
            'SELECT o.id FROM course_offerings o
              WHERE o.lecturer_id = ? OR EXISTS (SELECT 1 FROM offering_lecturers ol WHERE ol.offering_id = o.id AND ol.staff_id = ?)',
            [$staffId, $staffId]
        ), 'id'));
    }

    /** Offering ids the student is registered on (optionally for one semester). */
    public static function studiedOfferingIds(int $studentId, ?int $semesterId = null): array
    {
        $sql    = 'SELECT offering_id FROM course_registrations WHERE student_id = ? AND status <> "dropped"';
        $params = [$studentId];
        if ($semesterId !== null) {
            $sql     .= ' AND semester_id = ?';
            $params[] = $semesterId;
        }
        return array_map('intval', array_column(Database::select($sql, $params), 'offering_id'));
    }

    /** "1,2,3" for an IN (...) list; "0" when empty so the query still parses and matches nothing. */
    public static function inList(array $ids): string
    {
        return $ids === [] ? '0' : implode(',', array_map('intval', $ids));
    }
}
