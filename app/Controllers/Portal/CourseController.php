<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Models\Semester;
use App\Services\Elearning\ClassroomService;
use App\Services\Elearning\CourseAccess;
use App\Services\Elearning\ExamService;

/** "My Courses": each registered course with its lecturer, materials, online classes and exams. */
final class CourseController extends Controller
{
    use CurrentStudent;

    public function index(Request $request): string
    {
        $student  = $this->student();
        $semester = (new Semester())->current();
        $semesterId = $request->query('semester') === 'all' ? null : ($semester['id'] ?? null);

        $courses = Database::select(
            'SELECT o.id AS offering_id, o.section, o.delivery_mode, c.code, c.title, c.description,
                    COALESCE(cr.credit_hours, c.credit_hours) AS credit_hours, cr.approval_status, cr.status AS registration_status,
                    sem.name AS semester_name, ay.name AS academic_year,
                    CONCAT_WS(" ", lu.title, lu.first_name, lu.last_name) AS lecturer_name,
                    res.grade, res.is_published,
                    (SELECT COUNT(*) FROM course_materials m WHERE m.offering_id = o.id AND m.is_published = 1) AS materials,
                    (SELECT COUNT(*) FROM class_sessions s WHERE s.offering_id = o.id AND s.status <> "cancelled") AS sessions,
                    (SELECT COUNT(*) FROM online_exams e WHERE e.offering_id = o.id AND e.status <> "draft") AS exams
               FROM course_registrations cr
               JOIN course_offerings o ON o.id = cr.offering_id
               JOIN courses c          ON c.id = o.course_id
               JOIN semesters sem      ON sem.id = o.semester_id
               JOIN academic_years ay  ON ay.id = sem.academic_year_id
          LEFT JOIN staff ls           ON ls.id = o.lecturer_id
          LEFT JOIN users lu           ON lu.id = ls.user_id
          LEFT JOIN course_results res ON res.student_id = cr.student_id AND res.offering_id = o.id
              WHERE cr.student_id = ? AND cr.status <> "dropped"' . ($semesterId !== null ? ' AND cr.semester_id = ' . (int) $semesterId : '') . '
              ORDER BY ay.start_date DESC, sem.semester_number DESC, c.code',
            [$student['id']]
        );

        // The next live or upcoming online class for each course.
        $next = [];
        foreach (ClassroomService::sessions(array_column($courses, 'offering_id'), (int) $student['id']) as $session) {
            $phase = ClassroomService::phase($session);
            if (in_array($phase, ['live', 'upcoming'], true) && !isset($next[$session['offering_id']])) {
                $next[$session['offering_id']] = $session + ['phase' => $phase];
            }
        }

        return $this->view('portal.courses.index', [
            'pageTitle' => 'My Courses',
            'student'   => $student,
            'semester'  => $semester,
            'courses'   => $courses,
            'next'      => $next,
            'showAll'   => $semesterId === null,
        ]);
    }

    public function show(Request $request, string $id): string
    {
        $student  = $this->student();
        $offering = CourseAccess::studying((int) $id, (int) $student['id']);

        $sessions = array_map(static fn (array $s): array => $s + ['phase' => ClassroomService::phase($s), 'can_join' => ClassroomService::canJoin($s)],
            ClassroomService::sessions([(int) $id], (int) $student['id']));
        $exams = [];
        foreach (Database::select('SELECT id FROM online_exams WHERE offering_id = ? AND status <> "draft" ORDER BY opens_at', [(int) $id]) as $row) {
            $exam    = ExamService::find((int) $row['id']);
            $attempt = ExamService::attempt((int) $exam['id'], (int) $student['id']);
            $exams[] = $exam + ['phase' => ExamService::phase($exam), 'attempt' => $attempt === null ? null : ExamService::expireIfDue($attempt)];
        }
        $attendance = Database::selectOne(
            'SELECT COUNT(ar.id) AS total, SUM(ar.status IN ("present","late")) AS attended
               FROM attendance_records ar JOIN attendance_sessions s ON s.id = ar.session_id
              WHERE ar.student_id = ? AND s.offering_id = ?',
            [$student['id'], (int) $id]
        );
        $assessments = Database::select(
            'SELECT a.title, a.type, a.max_score, a.weight, a.due_date, sc.score, sc.status
               FROM assessments a
          LEFT JOIN assessment_scores sc ON sc.assessment_id = a.id AND sc.student_id = ?
              WHERE a.offering_id = ? AND a.is_published = 1
              ORDER BY a.due_date',
            [$student['id'], (int) $id]
        );

        return $this->view('portal.courses.show', [
            'pageTitle'   => $offering['code'] . ' - ' . $offering['title'],
            'student'     => $student,
            'offering'    => $offering,
            'materials'   => Database::select(
                'SELECT * FROM course_materials WHERE offering_id = ? AND is_published = 1 ORDER BY COALESCE(week, 999), created_at',
                [(int) $id]
            ),
            'sessions'    => $sessions,
            'exams'       => $exams,
            'attendance'  => $attendance,
            'assessments' => $assessments,
            'classmates'  => (int) Database::scalar('SELECT COUNT(*) FROM course_registrations WHERE offering_id = ? AND status <> "dropped"', [(int) $id]),
        ]);
    }
}
