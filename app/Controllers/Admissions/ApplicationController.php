<?php
declare(strict_types=1);

namespace App\Controllers\Admissions;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Application;
use App\Models\Intake;
use App\Models\Program;
use App\Services\AdmissionService;

final class ApplicationController extends Controller
{
    private Application $model;

    public function __construct()
    {
        $this->model = new Application();
    }

    public function index(Request $request): string
    {
        $this->authorize('admissions.view');
        $query = $this->model->listing()
            ->search((string) $request->query('q', ''), $this->model->searchable());

        if ($status = $request->query('status')) {
            $query->where('a.status', $status);
        }
        if ($intakeId = $request->query('intake_id')) {
            $query->where('a.intake_id', (int) $intakeId);
        }

        $result = $query->orderBy('a.submitted_at', 'DESC')
                        ->paginate($this->page($request), $this->perPage($request));

        return $this->view('admissions.applications.index', [
            'pageTitle' => 'Applications',
            'result'    => $result,
            'intakes'   => (new Intake())->all(),
            'stats'     => $this->model->countByStatus(),
        ]);
    }

    public function create(Request $request): string
    {
        $this->authorize('admissions.create');
        return $this->view('admissions.applications.create', [
            'pageTitle' => 'New Application',
            'intakes'   => Database::select("SELECT id, name FROM intakes WHERE status = 'open' ORDER BY name"),
            'programs'  => (new Program())->options(),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('admissions.create');
        $this->verifyCsrf($request);

        $data = $this->validate($request, [
            'intake_id'   => 'required|integer|exists:intakes,id',
            'program_id'  => 'required|integer|exists:programs,id',
            'first_name'  => 'required|max:80',
            'last_name'   => 'required|max:80',
            'email'       => 'required|email|max:150',
            'phone'       => 'required|max:30',
            'gender'      => 'required|in:male,female,other',
            'date_of_birth' => 'required|date',
            'nationality' => 'required|max:80',
            'national_id' => 'required|max:40',
            'study_mode'  => 'required|in:full_time,part_time,evening,distance',
        ]);

        $data['application_number'] = $this->model->nextApplicationNumber();
        $data['status']             = 'submitted';
        $data['submitted_at']       = date('Y-m-d H:i:s');

        $this->model->create($data);
        (new AdmissionService())->sendApplicationReceipt($data);

        $this->success('Application created.', '/admissions/applications');
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('admissions.view');
        $application = $this->model->detail((int) $id);
        if (!$application) { throw new HttpException(404); }

        return $this->view('admissions.applications.show', [
            'pageTitle'   => 'Application ' . $application['application_number'],
            'application' => $application,
            'documents'   => $this->model->documents((int) $id),
        ]);
    }

    public function review(Request $request, string $id): never
    {
        $this->authorize('admissions.review');
        $this->verifyCsrf($request);

        $data = $this->validate($request, [
            'status'  => 'required|in:under_review,shortlisted,accepted,rejected',
            'score'   => 'nullable|numeric',
            'remarks' => 'nullable|max:1000',
        ]);

        $result = (new AdmissionService())->review(
            (int) $id,
            $data['status'],
            $data['remarks'] ?? null,
            isset($data['score']) ? (float) $data['score'] : null,
            \App\Core\Auth::id()
        );

        if (!$result['ok']) {
            $this->error($result['message'], '/admissions/applications/' . $id);
        }

        $this->success($result['message'], '/admissions/applications/' . $id);
    }

    public function enrol(Request $request, string $id): never
    {
        $this->authorize('admissions.enrol');
        $this->verifyCsrf($request);

        $application = $this->model->detail((int) $id);
        if (!$application) { throw new HttpException(404); }

        $result = (new AdmissionService())->enrol((int) $id, \App\Core\Auth::id());
        if (!$result['ok']) {
            $this->error($result['message'], '/admissions/applications/' . $id);
        }

        $this->success($result['message'], '/admissions/applications/' . $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('admissions.delete');
        $this->verifyCsrf($request);
        $this->model->delete((int) $id);
        $this->success('Application deleted.', '/admissions/applications');
    }
}
