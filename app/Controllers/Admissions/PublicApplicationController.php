<?php
declare(strict_types=1);

namespace App\Controllers\Admissions;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Application;
use App\Models\Intake;
use App\Models\Program;

/**
 * Public-facing online application portal.
 * Routes: GET/POST /apply  |  GET/POST /apply/status
 */
final class PublicApplicationController extends Controller
{
    private Application $applicationModel;
    private Intake      $intakeModel;
    private Program     $programModel;

    public function __construct()
    {
        $this->applicationModel = new Application();
        $this->intakeModel      = new Intake();
        $this->programModel     = new Program();
    }

    /** Show the public application form. */
    public function show(Request $request): string
    {
        $intakes  = $this->openIntakes();
        $programs = $this->activePrograms();

        return $this->view('admissions.apply', [
            'pageTitle' => 'Apply for Admission',
            'intakes'   => $intakes,
            'programs'  => $programs,
        ]);
    }

    /** Handle submission of the public application form. */
    public function submit(Request $request): string
    {
        $this->verifyCsrf($request);

        $data = $this->validate($request, [
            'intake_id'          => 'required|integer|exists:intakes,id',
            'program_id'         => 'required|integer|exists:programs,id',
            'first_name'         => 'required|max:60',
            'last_name'          => 'required|max:60',
            'other_name'         => 'nullable|max:60',
            'email'              => 'required|email|max:150',
            'phone'              => 'required|max:20',
            'gender'             => 'required|in:male,female,other',
            'date_of_birth'      => 'required|date',
            'nationality'        => 'required|max:60',
            'national_id'        => 'required|max:30',
            'address'            => 'nullable|max:255',
            'county'             => 'nullable|max:60',
            'previous_school'    => 'nullable|max:120',
            'qualification'      => 'nullable|max:80',
            'grade_obtained'     => 'nullable|max:10',
            'year_completed'     => 'nullable|integer',
            'study_mode'         => 'required|in:full_time,part_time,evening,distance',
            'sponsor_type'       => 'nullable|in:self,government,scholarship,employer',
            'personal_statement' => 'nullable|max:2000',
        ]);

        $data['application_number'] = $this->applicationModel->nextApplicationNumber();
        $data['status']             = 'pending';
        $data['submitted_at']       = date('Y-m-d H:i:s');

        $id = $this->applicationModel->create($data);

        return $this->view('admissions.apply-success', [
            'pageTitle'         => 'Application Submitted',
            'applicationNumber' => $data['application_number'],
            'firstName'         => $data['first_name'],
        ]);
    }

    /** Show the application status-lookup form. */
    public function status(Request $request): string
    {
        return $this->view('admissions.apply-status', [
            'pageTitle'   => 'Check Application Status',
            'application' => null,
        ]);
    }

    /** Handle status lookup by application number. */
    public function lookup(Request $request): string
    {
        $this->verifyCsrf($request);

        $data = $this->validate($request, [
            'application_number' => 'required|max:30',
            'email'              => 'required|email|max:150',
        ]);

        $application = null;
        $row = \App\Core\Database::selectOne(
            'SELECT a.*, p.name AS program_name, p.code AS program_code, i.name AS intake_name
               FROM applications a
               JOIN programs p ON p.id = a.program_id
               JOIN intakes  i ON i.id = a.intake_id
              WHERE a.application_number = ? AND a.email = ?',
            [$data['application_number'], $data['email']]
        );

        if ($row === null) {
            \App\Core\Session::flash('error', 'No application found. Please check your application number and email.');
        } else {
            $application = $row;
        }

        return $this->view('admissions.apply-status', [
            'pageTitle'   => 'Check Application Status',
            'application' => $application,
        ]);
    }

    // ------------------------------------------------------------------ //
    //  Helpers                                                            //
    // ------------------------------------------------------------------ //

    private function openIntakes(): array
    {
        return \App\Core\Database::select(
            "SELECT id, name, code, application_open, application_close
               FROM intakes
              WHERE status = 'open'
              ORDER BY application_close ASC"
        );
    }

    private function activePrograms(): array
    {
        return \App\Core\Database::select(
            "SELECT p.id, p.code, p.name, p.award, p.level, p.study_mode,
                    p.application_fee, f.name AS faculty_name
               FROM programs p
               JOIN departments d ON d.id = p.department_id
               JOIN faculties   f ON f.id = d.faculty_id
              WHERE p.status = 'active'
              ORDER BY f.name, p.name"
        );
    }
}
