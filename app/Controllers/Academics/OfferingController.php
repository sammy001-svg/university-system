<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Staff;

final class OfferingController extends Controller
{
    private CourseOffering $model;

    public function __construct() { $this->model = new CourseOffering(); }

    public function index(Request $request): string
    {
        $this->authorize('offerings.view');
        $result = $this->model->listing()
            ->search((string) $request->query('q',''), $this->model->searchable())
            ->orderBy('ay.start_date','DESC')
            ->paginate($this->page($request), $this->perPage($request));

        return $this->view('academics.offerings.index', [
            'pageTitle' => 'Class Offerings',
            'result'    => $result,
            'semesters' => (new Semester())->all(),
        ]);
    }

    public function create(Request $request): string
    {
        $this->authorize('offerings.create');
        return $this->view('academics.offerings.form', [
            'pageTitle' => 'New Offering',
            'record'    => [],
            'isNew'     => true,
            'semesters' => Database::select("SELECT id, name FROM semesters ORDER BY id DESC"),
            'courses'   => Database::select("SELECT id, CONCAT(code,' - ',title) AS label FROM courses WHERE status='active' ORDER BY code"),
            'staff'     => (new \App\Models\User())->staffOptions(),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('offerings.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'course_id'   => 'required|integer|exists:courses,id',
            'semester_id' => 'required|integer|exists:semesters,id',
            'section'     => 'required|max:10',
            'capacity'    => 'required|integer',
            'lecturer_id' => 'nullable|integer|exists:staff,id',
            'delivery_mode' => 'required|in:physical,online,hybrid',
        ]);
        $this->model->create($data);
        $this->success('Offering created.', '/academics/offerings');
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('offerings.edit');
        $record = $this->findOrFail($this->model, (int) $id, 'Offering');
        return $this->view('academics.offerings.form', [
            'pageTitle' => 'Edit Offering',
            'record'    => $record,
            'isNew'     => false,
            'semesters' => Database::select("SELECT id, name FROM semesters ORDER BY id DESC"),
            'courses'   => Database::select("SELECT id, CONCAT(code,' - ',title) AS label FROM courses WHERE status='active' ORDER BY code"),
            'staff'     => (new \App\Models\User())->staffOptions(),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('offerings.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'capacity'      => 'required|integer',
            'lecturer_id'   => 'nullable|integer|exists:staff,id',
            'delivery_mode' => 'required|in:physical,online,hybrid',
            'status'        => 'required|in:open,closed,cancelled,completed',
        ]);
        $this->model->update((int) $id, $data);
        $this->success('Offering updated.', '/academics/offerings');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('offerings.delete');
        $this->verifyCsrf($request);
        $this->model->delete((int) $id);
        $this->success('Offering deleted.', '/academics/offerings');
    }

    public function roster(Request $request, string $id): string
    {
        $this->authorize('offerings.view');
        $offering = $this->findOrFail($this->model, (int) $id, 'Offering');
        $students = Database::select(
            'SELECT cr.*, s.admission_number, u.first_name, u.last_name, u.email
               FROM course_registrations cr
               JOIN students s ON s.id = cr.student_id
               JOIN users u    ON u.id = s.user_id
              WHERE cr.offering_id = ? AND cr.status = "registered"
              ORDER BY u.last_name, u.first_name',
            [(int) $id]
        );
        return $this->view('academics.offerings.roster', [
            'pageTitle' => 'Class Roster',
            'offering'  => $offering,
            'students'  => $students,
        ]);
    }

    public function generate(Request $request): never
    {
        $this->authorize('offerings.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, ['semester_id' => 'required|integer|exists:semesters,id']);
        $this->model->generateForSemester($data['semester_id']);
        $this->success('Offerings generated.', '/academics/offerings');
    }
}
