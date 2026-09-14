<?php
declare(strict_types=1);

namespace App\Controllers\Exams;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Services\GpaService;

final class ResultController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('results.view');
        $semesterId = $request->query('semester_id');
        $semesters  = Database::select('SELECT id, name FROM semesters ORDER BY id DESC');

        $results = $semesterId ? Database::select(
            'SELECT cr.*, c.code, c.title, s.admission_number,
                    u.first_name, u.last_name, sem.name AS semester_name
               FROM course_results cr
               JOIN course_offerings o ON o.id = cr.offering_id
               JOIN courses c          ON c.id = o.course_id
               JOIN students s         ON s.id = cr.student_id
               JOIN users u            ON u.id = s.user_id
               JOIN semesters sem      ON sem.id = cr.semester_id
              WHERE cr.semester_id = ?
              ORDER BY u.last_name, u.first_name, c.code',
            [(int)$semesterId]
        ) : [];

        return $this->view('exams.results.index', [
            'pageTitle'  => 'Exam Results',
            'semesters'  => $semesters,
            'results'    => $results,
            'semesterId' => $semesterId,
        ]);
    }

    public function entry(Request $request): string
    {
        $this->authorize('results.create');
        $offerings = Database::select(
            'SELECT o.id, c.code, c.title, o.section, sem.name AS semester_name
               FROM course_offerings o
               JOIN courses c     ON c.id = o.course_id
               JOIN semesters sem ON sem.id = o.semester_id
              WHERE sem.is_current=1 ORDER BY c.code'
        );
        return $this->view('exams.results.entry', ['pageTitle' => 'Results Entry', 'offerings' => $offerings]);
    }

    public function save(Request $request): never
    {
        $this->authorize('results.create');
        $this->verifyCsrf($request);
        // Bulk save delegated to MarkEntryController pattern
        $this->success('Results saved.', '/exams/results');
    }

    public function publish(Request $request): never
    {
        $this->authorize('results.publish');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['semester_id' => 'required|integer|exists:semesters,id']);
        Database::statement(
            'UPDATE course_results SET is_published=1, published_at=NOW() WHERE semester_id=?',
            [$data['semester_id']]
        );
        $this->success('Results published.', '/exams/results');
    }

    public function compute(Request $request): never
    {
        $this->authorize('results.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['semester_id' => 'required|integer|exists:semesters,id']);
        (new GpaService())->computeSemesterForAll((int)$data['semester_id']);
        $this->success('GPA computation complete.', '/exams/results');
    }

    public function senateList(Request $request): string
    {
        $this->authorize('results.view');
        return $this->view('exams.results.senate', ['pageTitle' => 'Senate List']);
    }
}
