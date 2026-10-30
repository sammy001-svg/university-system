<?php
declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Staff;
use App\Models\User;

final class StaffController extends Controller
{
    private Staff $model;
    public function __construct() { $this->model = new Staff(); }

    public function index(Request $request): string
    {
        $this->authorize('staff.view');
        $result = $this->model->listing()
            ->search((string) $request->query('q',''), $this->model->searchable())
            ->orderBy('u.last_name','ASC')
            ->paginate($this->page($request), $this->perPage($request));
        return $this->view('staff.index', ['pageTitle' => 'Staff', 'result' => $result]);
    }

    public function create(Request $request): string
    {
        $this->authorize('staff.create');
        return $this->view('staff.form', [
            'pageTitle'   => 'Add Staff',
            'record'      => [],
            'isNew'       => true,
            'departments' => Database::select("SELECT id, name FROM departments WHERE status='active' ORDER BY name"),
            'colleges'    => Database::select("SELECT id, name FROM faculties WHERE status='active' ORDER BY name"),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('staff.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'first_name'      => 'required|max:80',
            'last_name'       => 'required|max:80',
            'email'           => 'required|email|max:150',
            'phone'           => 'nullable|max:30',
            'department_id'   => 'nullable|integer|exists:departments,id',
            'faculty_id'      => 'nullable|integer|exists:faculties,id',
            'designation'     => 'nullable|max:120',
            'staff_category'  => 'required|in:academic,administrative,support,technical',
            'employment_type' => 'required|in:permanent,contract,part_time,visiting,intern',
            'date_joined'     => 'nullable|date',
            'basic_salary'    => 'nullable|numeric',
        ]);

        $userModel = new User();
        $username  = $userModel->generateUsername($data['first_name'], $data['last_name']);
        $userId = $userModel->createAccount([
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'user_type'  => 'staff',
            'status'     => 'active',
            'username'   => $username,
        ]);

        $prefix     = (string) \App\Core\Setting::get('institution_short_name', 'STF');
        $count      = (int) Database::scalar('SELECT COUNT(*) FROM staff') + 1;
        $staffNumber = $prefix . '/' . str_pad((string)$count, 4, '0', STR_PAD_LEFT);

        $this->model->create([
            'user_id'         => $userId,
            'staff_number'    => $staffNumber,
            'department_id'   => $data['department_id'] ?? null,
            'faculty_id'      => $this->collegeFor($data),
            'designation'     => $data['designation'] ?? null,
            'staff_category'  => $data['staff_category'],
            'employment_type' => $data['employment_type'],
            'date_joined'     => $data['date_joined'] ?? null,
            'basic_salary'    => $data['basic_salary'] ?? 0,
            'status'          => 'active',
        ]);

        $this->success('Staff member added.', '/staff');
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('staff.view');
        $staff = $this->staffProfile((int)$id);
        return $this->view('staff.show', ['pageTitle' => 'Staff Profile', 'staff' => $staff]);
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('staff.edit');
        $staff = $this->staffProfile((int)$id);
        return $this->view('staff.form', [
            'pageTitle'   => 'Edit Staff',
            'record'      => $staff,
            'isNew'       => false,
            'departments' => Database::select("SELECT id, name FROM departments WHERE status='active' ORDER BY name"),
            'colleges'    => Database::select("SELECT id, name FROM faculties WHERE status='active' ORDER BY name"),
        ]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('staff.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'designation'     => 'nullable|max:120',
            'department_id'   => 'nullable|integer|exists:departments,id',
            'faculty_id'      => 'nullable|integer|exists:faculties,id',
            'staff_category'  => 'required|in:academic,administrative,support,technical',
            'employment_type' => 'required|in:permanent,contract,part_time,visiting,intern',
            'basic_salary'    => 'nullable|numeric',
            'status'          => 'required|in:active,on_leave,suspended,terminated,retired',
        ]);
        $data['faculty_id'] = $this->collegeFor($data);
        $this->model->update((int)$id, $data);
        $this->success('Staff updated.', '/staff/' . $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('staff.delete');
        $this->verifyCsrf($request);
        $this->model->delete((int)$id);
        $this->success('Staff record deleted.', '/staff');
    }

    /** A department fixes the college; without one, the chosen college stands. */
    private function collegeFor(array $data): ?int
    {
        if (!empty($data['department_id'])) {
            return (int) Database::scalar('SELECT faculty_id FROM departments WHERE id = ?', [(int) $data['department_id']]);
        }
        return empty($data['faculty_id']) ? null : (int) $data['faculty_id'];
    }

    private function staffProfile(int $id): array
    {
        $row = Database::selectOne(
            'SELECT st.*, u.first_name, u.last_name, u.email, u.phone, u.gender, u.avatar, u.user_type,
                    d.name AS department_name, f.name AS faculty_name
               FROM staff st
               JOIN users u         ON u.id = st.user_id
          LEFT JOIN departments d   ON d.id = st.department_id
          LEFT JOIN faculties f     ON f.id = COALESCE(d.faculty_id, st.faculty_id)
              WHERE st.id=?',
            [$id]
        );
        if (!$row) { throw new HttpException(404); }
        return $row;
    }
}
