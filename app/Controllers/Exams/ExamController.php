<?php
declare(strict_types=1);

namespace App\Controllers\Exams;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Exam;

final class ExamController extends Controller
{
    private Exam $model;
    public function __construct() { $this->model = new Exam(); }

    public function index(Request $request): string
    {
        $this->authorize('exams.view');
        $result = Database::select(
            'SELECT e.*, c.code, c.title, sem.name AS semester_name, r.name AS room_name
               FROM exams e
               JOIN course_offerings o ON o.id = e.offering_id
               JOIN courses c          ON c.id = o.course_id
               JOIN semesters sem      ON sem.id = e.semester_id
          LEFT JOIN rooms r            ON r.id = e.room_id
              ORDER BY e.exam_date DESC, e.start_time'
        );
        return $this->view('exams.index', ['pageTitle' => 'Examinations', 'exams' => $result]);
    }

    public function create(Request $request): string
    {
        $this->authorize('exams.create');
        return $this->view('exams.form', [
            'pageTitle' => 'Schedule Exam',
            'record'    => [],
            'isNew'     => true,
            'offerings' => Database::select('SELECT o.id, c.code, c.title, o.section FROM course_offerings o JOIN courses c ON c.id=o.course_id WHERE o.status="open" ORDER BY c.code'),
            'semesters' => Database::select('SELECT id, name FROM semesters ORDER BY id DESC'),
            'rooms'     => Database::select("SELECT id, CONCAT(code,' – ',name) AS label FROM rooms ORDER BY code"),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('exams.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'offering_id'    => 'required|integer|exists:course_offerings,id',
            'semester_id'    => 'required|integer|exists:semesters,id',
            'exam_type'      => 'required|in:main,supplementary,special,retake',
            'exam_date'      => 'required|date',
            'start_time'     => 'required',
            'duration_mins'  => 'required|integer',
            'room_id'        => 'nullable|integer|exists:rooms,id',
            'max_score'      => 'required|numeric',
        ]);
        $this->model->create($data);
        $this->success('Exam scheduled.', '/exams');
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('exams.edit');
        $record = $this->findOrFail($this->model, (int) $id, 'Exam');
        return $this->view('exams.form', [
            'pageTitle' => 'Edit Exam',
            'record'    => $record,
            'isNew'     => false,
            'offerings' => Database::select('SELECT o.id, c.code, c.title, o.section FROM course_offerings o JOIN courses c ON c.id=o.course_id ORDER BY c.code'),
            'semesters' => Database::select('SELECT id, name FROM semesters ORDER BY id DESC'),
            'rooms'     => Database::select("SELECT id, CONCAT(code,' – ',name) AS label FROM rooms ORDER BY code"),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('exams.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'exam_date'     => 'required|date',
            'start_time'    => 'required',
            'duration_mins' => 'required|integer',
            'room_id'       => 'nullable|integer|exists:rooms,id',
            'status'        => 'required|in:scheduled,ongoing,completed,cancelled',
        ]);
        $this->model->update((int) $id, $data);
        $this->success('Exam updated.', '/exams');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('exams.delete');
        $this->verifyCsrf($request);
        $this->model->delete((int) $id);
        $this->success('Exam deleted.', '/exams');
    }
}
