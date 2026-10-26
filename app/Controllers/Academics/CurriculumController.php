<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Program;

final class CurriculumController extends Controller
{
    public function show(Request $request, string $id): string
    {
        $this->authorize('academics.view');
        $programs = new Program();
        $program  = $programs->withDepartment((int) $id);
        if (!$program) { throw new HttpException(404); }

        $curriculum = $programs->curriculum((int) $id);

        return $this->view('academics.curriculum.show', [
            'pageTitle'   => $program['name'] . ' – Curriculum',
            'program'     => $program,
            'curriculum'  => $curriculum,
            'planCredits' => $programs->totalCredits((int) $id),
            'options'     => $programs->courseOptions((int) $id),
            'courses'     => Database::select("SELECT id, CONCAT(code, ' – ', title) AS label FROM courses WHERE status='active' ORDER BY code"),
        ]);
    }

    public function add(Request $request, string $id): never
    {
        $this->authorize('academics.create');
        $this->verifyCsrf($request);
        $back = '/academics/programs/' . $id . '/curriculum';
        $data = $this->validate($request, [
            'course_id'       => 'nullable|integer|exists:courses,id',
            'slot_label'      => 'nullable|max:120',
            'year_of_study'   => 'required|integer|between:1,6',
            'semester_number' => 'required|integer|between:1,3',
            'credit_hours'    => 'required|integer|between:0,12',
            'course_type'     => 'required|in:core,common,elective,minor,concentration,audit',
            'note'            => 'nullable|max:255',
        ]);
        if (empty($data['course_id']) && trim((string) ($data['slot_label'] ?? '')) === '') {
            $this->error('Choose a course, or name the slot (for example "Minor course").', $back);
        }
        if (!empty($data['course_id'])) {
            $data['slot_label'] = null;
            $exists = Database::scalar(
                'SELECT id FROM program_courses WHERE program_id=? AND course_id=? AND year_of_study=? AND semester_number=?',
                [(int) $id, $data['course_id'], $data['year_of_study'], $data['semester_number']]
            );
            if ($exists) { $this->error('That course is already in this term of the curriculum.', $back); }
        }

        $sort = (int) Database::scalar(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM program_courses WHERE program_id=? AND year_of_study=? AND semester_number=?',
            [(int) $id, $data['year_of_study'], $data['semester_number']]
        );
        Database::statement(
            'INSERT INTO program_courses (program_id, course_id, slot_label, year_of_study, semester_number, credit_hours, course_type, sort_order, note)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [
                (int) $id, $data['course_id'] ?: null, $data['slot_label'] ?: null, $data['year_of_study'],
                $data['semester_number'], $data['credit_hours'], $data['course_type'], $sort, $data['note'] ?: null,
            ]
        );
        $this->success('Added to the curriculum.', $back);
    }

    public function remove(Request $request, string $program, string $id): never
    {
        $this->authorize('academics.delete');
        $this->verifyCsrf($request);
        Database::statement('DELETE FROM program_courses WHERE id=? AND program_id=?', [(int) $id, (int) $program]);
        $this->success('Removed from the curriculum.', '/academics/programs/' . $program . '/curriculum');
    }

    public function addPrerequisite(Request $request, string $id): never
    {
        $this->authorize('academics.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['prerequisite_id' => 'required|integer|exists:courses,id']);
        Database::statement(
            'INSERT IGNORE INTO course_prerequisites (course_id, prerequisite_course_id) VALUES (?,?)',
            [(int)$id, $data['prerequisite_id']]
        );
        $this->success('Prerequisite added.', '/academics/courses/' . $id);
    }

    public function removePrerequisite(Request $request, string $course, string $id): never
    {
        $this->authorize('academics.delete');
        $this->verifyCsrf($request);
        Database::statement('DELETE FROM course_prerequisites WHERE course_id=? AND prerequisite_course_id=?', [(int)$course, (int)$id]);
        $this->success('Prerequisite removed.', '/academics/courses/' . $course);
    }
}
