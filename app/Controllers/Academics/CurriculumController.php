<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;

final class CurriculumController extends Controller
{
    public function show(Request $request, string $id): string
    {
        $this->authorize('academics.view');
        $program = Database::selectOne('SELECT * FROM programs WHERE id=?', [(int)$id]);
        if (!$program) { throw new HttpException(404); }

        $curriculum = Database::select(
            'SELECT pc.*, c.code, c.title, c.credit_hours, c.level, c.semester_number
               FROM program_courses pc
               JOIN courses c ON c.id = pc.course_id
              WHERE pc.program_id = ?
              ORDER BY pc.year_of_study, c.semester_number, c.code',
            [(int)$id]
        );

        $available = Database::select(
            "SELECT id, code, title, credit_hours FROM courses WHERE status='active'
              AND id NOT IN (SELECT course_id FROM program_courses WHERE program_id=?)
              ORDER BY code",
            [(int)$id]
        );

        return $this->view('academics.curriculum.show', [
            'pageTitle'  => $program['name'] . ' – Curriculum',
            'program'    => $program,
            'curriculum' => $curriculum,
            'available'  => $available,
        ]);
    }

    public function add(Request $request, string $id): never
    {
        $this->authorize('academics.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'course_id'   => 'required|integer|exists:courses,id',
            'year_of_study' => 'required|integer',
            'is_elective' => 'nullable|boolean',
        ]);

        $exists = Database::scalar('SELECT id FROM program_courses WHERE program_id=? AND course_id=?', [(int)$id, $data['course_id']]);
        if ($exists) { $this->error('Course already in curriculum.', '/programs/' . $id . '/curriculum'); }

        Database::statement(
            'INSERT INTO program_courses (program_id, course_id, year_of_study, is_elective) VALUES (?,?,?,?)',
            [(int)$id, $data['course_id'], $data['year_of_study'], !empty($data['is_elective']) ? 1 : 0]
        );
        $this->success('Course added to curriculum.', '/programs/' . $id . '/curriculum');
    }

    public function remove(Request $request, string $id, string $courseId): never
    {
        $this->authorize('academics.delete');
        $this->verifyCsrf($request);
        Database::statement('DELETE FROM program_courses WHERE program_id=? AND course_id=?', [(int)$id, (int)$courseId]);
        $this->success('Course removed from curriculum.', '/programs/' . $id . '/curriculum');
    }

    public function addPrerequisite(Request $request, string $id): never
    {
        $this->authorize('academics.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['prerequisite_id' => 'required|integer|exists:courses,id']);
        Database::statement(
            'INSERT IGNORE INTO course_prerequisites (course_id, prerequisite_id) VALUES (?,?)',
            [(int)$id, $data['prerequisite_id']]
        );
        $this->success('Prerequisite added.', '/courses/' . $id);
    }

    public function removePrerequisite(Request $request, string $id, string $prereqId): never
    {
        $this->authorize('academics.delete');
        $this->verifyCsrf($request);
        Database::statement('DELETE FROM course_prerequisites WHERE course_id=? AND prerequisite_id=?', [(int)$id, (int)$prereqId]);
        $this->success('Prerequisite removed.', '/courses/' . $id);
    }
}
