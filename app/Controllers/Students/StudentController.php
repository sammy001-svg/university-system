<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Hash;
use App\Core\Mailer;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Upload;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Intake;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;

final class StudentController extends Controller
{
    private Student $students;
    private User $users;

    public function __construct()
    {
        $this->students = new Student();
        $this->users    = new User();
    }

    private function filtered(Request $request): QueryBuilder
    {
        $query = $this->students->listing()
            ->search((string) $request->query('q', ''), $this->students->searchable());

        if ($request->filled('program_id')) {
            $query->where('s.program_id', $request->query('program_id'));
        }
        if ($request->filled('year_of_study')) {
            $query->where('s.year_of_study', $request->query('year_of_study'));
        }
        if ($request->filled('status')) {
            $query->where('s.status', $request->query('status'));
        }
        if ($request->filled('faculty_id')) {
            $query->where('f.id', $request->query('faculty_id'));
        }
        if ($request->filled('study_mode')) {
            $query->where('s.study_mode', $request->query('study_mode'));
        }
        return $query;
    }

    public function index(Request $request): string
    {
        $this->authorize('students.view');

        $result = $this->filtered($request)
            ->orderBy('s.admission_number', 'ASC')
            ->paginate($this->page($request), $this->perPage($request, 25));

        return $this->view('students.index', [
            'pageTitle' => 'Students',
            'result'    => $result,
            'programs'  => (new Program())->options(),
            'faculties' => (new Faculty())->options('name'),
            'counts'    => $this->students->countByStatus(),
        ]);
    }

    public function export(Request $request): never
    {
        $this->authorize('students.view');

        $rows = $this->filtered($request)->orderBy('s.admission_number', 'ASC')->limit(5000)->get();
        $out  = array_map(static fn (array $r): array => [
            $r['admission_number'], $r['first_name'], $r['last_name'], $r['email'], $r['phone'],
            $r['program_code'], $r['program_name'], $r['year_of_study'], $r['study_mode'],
            $r['cgpa'], $r['status'], $r['admission_date'],
        ], $rows);

        Response::csv($out, 'students-' . date('Ymd-His') . '.csv', [
            'Admission No', 'First name', 'Last name', 'Email', 'Phone', 'Programme code',
            'Programme', 'Year', 'Mode', 'CGPA', 'Status', 'Admitted',
        ]);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('students.view');
        $student = $this->students->profile((int) $id);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $currentSemester = (new Semester())->current();

        return $this->view('students.show', [
            'pageTitle'     => $this->students->fullName($student),
            'student'       => $student,
            'registrations' => $this->students->registrations((int) $id, $currentSemester['id'] ?? null),
            'history'       => $this->students->academicHistory((int) $id),
            'guardians'     => $this->students->guardians((int) $id),
            'balance'       => $this->students->balance((int) $id),
            'billed'        => $this->students->totalBilled((int) $id),
            'paid'          => $this->students->totalPaid((int) $id),
            'attendance'    => $this->students->attendanceRate((int) $id),
            'invoices'      => Database::select(
                'SELECT * FROM invoices WHERE student_id = ? ORDER BY issue_date DESC LIMIT 10',
                [$id]
            ),
            'hostel'        => Database::selectOne(
                'SELECT ha.*, hr.room_number, h.name AS hostel_name
                   FROM hostel_allocations ha
                   JOIN hostel_rooms hr ON hr.id = ha.hostel_room_id
                   JOIN hostels h       ON h.id = hr.hostel_id
                  WHERE ha.student_id = ? AND ha.status IN ("allocated","checked_in")
                  ORDER BY ha.id DESC LIMIT 1',
                [$id]
            ),
            'loans'         => Database::select(
                'SELECT bl.*, b.title FROM book_loans bl
                   JOIN books b ON b.id = bl.book_id
                  WHERE bl.user_id = ? ORDER BY bl.issued_at DESC LIMIT 5',
                [$student['user_id']]
            ),
            'discipline'    => Database::select(
                'SELECT * FROM disciplinary_cases WHERE student_id = ? ORDER BY incident_date DESC LIMIT 5',
                [$id]
            ),
        ]);
    }

    public function create(Request $request): string
    {
        $this->authorize('students.create');
        return $this->view('students.form', [
            'pageTitle' => 'Admit a student',
            'student'   => [],
            'programs'  => (new Program())->options(),
            'intakes'   => (new Intake())->options('name'),
            'isNew'     => true,
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('students.create');
        $this->verifyCsrf($request);

        $data = $this->validate($request, [
            'first_name'    => 'required|max:80',
            'last_name'     => 'required|max:80',
            'email'         => 'required|email|max:150|unique:users,email',
            'phone'         => 'nullable|phone',
            'gender'        => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'program_id'    => 'required|integer|exists:programs,id',
            'intake_id'     => 'nullable|integer',
            'year_of_study' => 'required|integer|between:1,6',
            'study_mode'    => 'required|in:full_time,part_time,evening,distance',
            'national_id'   => 'nullable|max:40',
            'county'        => 'nullable|max:80',
        ]);

        $program = (new Program())->find((int) $data['program_id']);
        if ($program === null) {
            $this->error('Select a valid programme.', '/students/create');
        }

        $password  = Hash::tempPassword();
        $studentId = Database::transaction(function () use ($data, $request, $program, $password) {
            $username = $this->users->generateUsername((string) $data['first_name'], (string) $data['last_name']);

            $userId = $this->users->createAccount([
                'username'             => $username,
                'email'                => $data['email'],
                'first_name'           => $data['first_name'],
                'last_name'            => $data['last_name'],
                'other_name'           => $request->input('other_name'),
                'gender'               => $data['gender'] ?: null,
                'date_of_birth'        => $data['date_of_birth'] ?: null,
                'phone'                => $data['phone'],
                'user_type'            => 'student',
                'status'               => 'active',
                'must_change_password' => 1,
            ], $this->studentRoleIds(), $password, Auth::id());

            $admissionNumber = trim((string) $request->input('admission_number'))
                ?: $this->students->nextAdmissionNumber((string) $program['code']);

            return $this->students->create([
                'user_id'                    => $userId,
                'admission_number'           => $admissionNumber,
                'registration_number'        => $request->input('registration_number') ?: $admissionNumber,
                'program_id'                 => (int) $data['program_id'],
                'intake_id'                  => $data['intake_id'] ?: null,
                'year_of_study'              => (int) $data['year_of_study'],
                'current_semester'           => (int) $request->input('current_semester', 1),
                'study_mode'                 => $data['study_mode'],
                'admission_date'             => $request->input('admission_date') ?: date('Y-m-d'),
                'sponsor_type'               => $request->input('sponsor_type', 'self'),
                'sponsor_name'               => $request->input('sponsor_name'),
                'nationality'                => $request->input('nationality', 'Kenyan'),
                'national_id'                => $data['national_id'],
                'county'                     => $data['county'],
                'physical_address'           => $request->input('physical_address'),
                'postal_address'             => $request->input('postal_address'),
                'city'                       => $request->input('city'),
                'emergency_contact_name'     => $request->input('emergency_contact_name'),
                'emergency_contact_phone'    => $request->input('emergency_contact_phone'),
                'emergency_contact_relation' => $request->input('emergency_contact_relation'),
                'previous_school'            => $request->input('previous_school'),
                'previous_qualification'     => $request->input('previous_qualification'),
                'previous_grade'             => $request->input('previous_grade'),
                'status'                     => 'active',
            ]);
        });

        Audit::created('student', $studentId, ['email' => $data['email']], 'students');
        Mailer::send(
            (string) $data['email'],
            'Your student account',
            '<p>Dear ' . e($data['first_name']) . ',</p><p>Your student account is ready. '
            . 'Your temporary password is <strong>' . e($password) . '</strong>.</p>'
        );

        Session::flash('success', 'Student admitted. Temporary password: ' . $password);
        $this->redirect('/students/' . $studentId);
    }

    private function studentRoleIds(): array
    {
        $id = Database::scalar("SELECT id FROM roles WHERE slug = 'student' LIMIT 1");
        return $id === null ? [] : [(int) $id];
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('students.edit');
        $student = $this->students->profile((int) $id);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        return $this->view('students.form', [
            'pageTitle' => 'Edit student record',
            'student'   => $student,
            'programs'  => (new Program())->options(),
            'intakes'   => (new Intake())->options('name'),
            'isNew'     => false,
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('students.edit');
        $this->verifyCsrf($request);

        $student = $this->students->profile((int) $id);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $data = $this->validate($request, [
            'first_name'          => 'required|max:80',
            'last_name'           => 'required|max:80',
            'email'               => 'required|email|max:150|unique:users,email,' . $student['user_id'],
            'phone'               => 'nullable|phone',
            'gender'              => 'nullable|in:male,female,other',
            'date_of_birth'       => 'nullable|date',
            'program_id'          => 'required|integer|exists:programs,id',
            'year_of_study'       => 'required|integer|between:1,6',
            'current_semester'    => 'required|integer|between:1,4',
            'study_mode'          => 'required|in:full_time,part_time,evening,distance',
            'registration_number' => 'nullable|max:40',
            'status'              => 'required|in:active,deferred,suspended,graduated,withdrawn,expelled,alumni',
        ]);

        Database::transaction(function () use ($data, $request, $student, $id) {
            $this->users->update((int) $student['user_id'], [
                'first_name'    => $data['first_name'],
                'last_name'     => $data['last_name'],
                'other_name'    => $request->input('other_name'),
                'email'         => $data['email'],
                'phone'         => $data['phone'],
                'gender'        => $data['gender'] ?: null,
                'date_of_birth' => $data['date_of_birth'] ?: null,
            ]);

            $avatar = $request->file('avatar');
            if ($avatar !== null) {
                $stored = Upload::store($avatar, 'avatars', Upload::IMAGES, 3);
                $this->users->update((int) $student['user_id'], ['avatar' => $stored['path']]);
            }

            $this->students->update((int) $id, [
                'registration_number'        => $data['registration_number'],
                'program_id'                 => (int) $data['program_id'],
                'intake_id'                  => $request->input('intake_id') ?: null,
                'year_of_study'              => (int) $data['year_of_study'],
                'current_semester'           => (int) $data['current_semester'],
                'study_mode'                 => $data['study_mode'],
                'admission_date'             => $request->input('admission_date') ?: null,
                'sponsor_type'               => $request->input('sponsor_type', 'self'),
                'sponsor_name'               => $request->input('sponsor_name'),
                'nationality'                => $request->input('nationality'),
                'national_id'                => $request->input('national_id'),
                'county'                     => $request->input('county'),
                'city'                       => $request->input('city'),
                'physical_address'           => $request->input('physical_address'),
                'postal_address'             => $request->input('postal_address'),
                'emergency_contact_name'     => $request->input('emergency_contact_name'),
                'emergency_contact_phone'    => $request->input('emergency_contact_phone'),
                'emergency_contact_relation' => $request->input('emergency_contact_relation'),
                'previous_school'            => $request->input('previous_school'),
                'previous_qualification'     => $request->input('previous_qualification'),
                'previous_grade'             => $request->input('previous_grade'),
                'status'                     => $data['status'],
            ]);
        });

        Audit::updated('student', (int) $id, $student, $data, 'students');
        $this->success('Student record updated.', '/students/' . $id);
    }

    public function changeStatus(Request $request, string $id): never
    {
        $this->authorize('students.status');
        $this->verifyCsrf($request);

        $student = $this->students->profile((int) $id);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $status  = (string) $request->input('status');
        $allowed = ['active', 'deferred', 'suspended', 'graduated', 'withdrawn', 'expelled', 'alumni'];
        if (!in_array($status, $allowed, true)) {
            $this->error('Invalid status.', '/students/' . $id);
        }

        $this->students->update((int) $id, [
            'status'          => $status,
            'completion_date' => in_array($status, ['graduated', 'alumni'], true) ? date('Y-m-d') : null,
        ]);
        // Suspending, expelling or withdrawing a student also closes portal access.
        $this->users->update((int) $student['user_id'], [
            'status' => in_array($status, ['suspended', 'expelled', 'withdrawn'], true) ? 'suspended' : 'active',
        ]);

        Audit::log('status_change', 'students', 'students', $id, 'Status set to ' . $status);
        $this->success('Student status changed to ' . humanize($status) . '.', '/students/' . $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('students.delete');
        $this->verifyCsrf($request);

        $student = $this->students->profile((int) $id);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $hasResults = (int) Database::scalar('SELECT COUNT(*) FROM course_results WHERE student_id = ?', [$id]);
        if ($hasResults > 0) {
            $this->error(
                'This student has examination results on file. Set the status to withdrawn instead of deleting.',
                '/students/' . $id
            );
        }

        Database::transaction(function () use ($id, $student) {
            $this->students->delete((int) $id);
            $this->users->delete((int) $student['user_id']);
        });

        Audit::deleted('student', (int) $id, $student, 'students');
        $this->success('Student record removed.', '/students');
    }
}
