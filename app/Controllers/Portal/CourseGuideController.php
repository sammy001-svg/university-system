<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Setting;
use App\Models\FeeStructure;
use App\Models\Program;
use App\Services\Academic\DegreeAudit;
use App\Services\Elearning\CourseAccess;

/**
 * The student's course guide: their programme's plan of study, semester by semester,
 * straight from the academic catalogue - with each course marked as passed, in
 * progress or still to take, the elective / concentration / minor lists, and the
 * grading scale. Printable.
 */
final class CourseGuideController extends Controller
{
    use CurrentStudent;

    public function show(Request $request): string
    {
        $student  = $this->student();
        $programs = new Program();
        $program  = $programs->withDepartment((int) $student['program_id']);
        $plan     = $programs->curriculum((int) $student['program_id']);

        // The student's standing against each line of the plan (from the degree audit).
        $audit  = DegreeAudit::for((int) $student['id']);
        $status = [];
        foreach ($audit['terms'] ?? [] as $semesters) {
            foreach ($semesters as $items) {
                foreach ($items as $item) {
                    $status[(int) $item['row']['id']] = $item;
                }
            }
        }

        // Descriptions and prerequisites of the courses in the plan.
        $courseIds = [];
        foreach ($plan as $semesters) {
            foreach ($semesters as $rows) {
                foreach ($rows as $row) {
                    if ($row['course_id']) {
                        $courseIds[] = (int) $row['course_id'];
                    }
                }
            }
        }
        $in = CourseAccess::inList($courseIds);
        $descriptions = array_column(Database::select("SELECT id, description FROM courses WHERE id IN ({$in}) AND description IS NOT NULL AND description <> ''"), 'description', 'id');
        $prerequisites = [];
        foreach (Database::select(
            "SELECT cp.course_id, c.code FROM course_prerequisites cp JOIN courses c ON c.id = cp.prerequisite_course_id WHERE cp.course_id IN ({$in}) ORDER BY c.code"
        ) as $p) {
            $prerequisites[(int) $p['course_id']][] = $p['code'];
        }
        // Which of them are on offer in the current semester.
        $offered = array_flip(array_map('intval', array_column(Database::select(
            "SELECT DISTINCT o.course_id FROM course_offerings o JOIN semesters s ON s.id = o.semester_id AND s.is_current = 1
              WHERE o.status = 'open' AND o.course_id IN ({$in})"
        ), 'course_id')));

        $minor = $student['minor_program_id'] ? [
            'program' => $programs->withDepartment((int) $student['minor_program_id']),
            'courses' => ($programs->courseOptions((int) $student['minor_program_id'])['minor'] ?? []),
        ] : null;

        return $this->view('portal.course-guide', [
            'pageTitle'     => 'Course Guide',
            'student'       => $student,
            'program'       => $program,
            'plan'          => $plan,
            'status'        => $status,
            'audit'         => $audit,
            'descriptions'  => $descriptions,
            'prerequisites' => $prerequisites,
            'offered'       => $offered,
            'options'       => $programs->courseOptions((int) $student['program_id']),
            'minor'         => $minor,
            'totalCredits'  => max($programs->totalCredits((int) $student['program_id']), (int) ($program['total_credit_hours'] ?? 0)),
            'standing'      => FeeStructure::classStanding($student),
            'grades'        => Database::select('SELECT * FROM grade_scales ORDER BY min_score DESC'),
            'minCgpa'       => (float) Setting::get('graduation_min_cgpa', 2.0),
        ]);
    }
}
