<?php
declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\LeaveRequest;

final class LeaveController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('hr.view');
        $leaves = Database::select(
            'SELECT lr.*, lt.name AS leave_type_name, u.first_name, u.last_name, st.staff_number
               FROM leave_requests lr
               JOIN leave_types lt ON lt.id = lr.leave_type_id
               JOIN staff st       ON st.id = lr.staff_id
               JOIN users u        ON u.id = st.user_id
              ORDER BY lr.created_at DESC'
        );
        return $this->view('staff.leave.index', ['pageTitle' => 'Leave Requests', 'leaves' => $leaves]);
    }

    public function create(Request $request): string
    {
        $this->authorize('hr.create');
        return $this->view('staff.leave.create', [
            'pageTitle'  => 'Apply for Leave',
            'leaveTypes' => Database::select('SELECT id, name, days_allowed, is_paid FROM leave_types ORDER BY name'),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('hr.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'leave_type_id' => 'required|integer|exists:leave_types,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date',
            'reason'        => 'nullable|max:500',
        ]);

        $staff = Database::selectOne('SELECT id FROM staff WHERE user_id=?', [Auth::id()]);
        if (!$staff) { $this->error('No staff profile found.', '/hr/leave'); }

        $data['staff_id'] = $staff['id'];
        $data['status']   = 'pending';
        (new LeaveRequest())->create($data);
        $this->success('Leave request submitted.', '/hr/leave');
    }

    public function decide(Request $request, string $id): never
    {
        $this->authorize('hr.manage');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'status'  => 'required|in:approved,rejected',
            'remarks' => 'nullable|max:500',
        ]);
        Database::statement(
            'UPDATE leave_requests SET status=?, decided_by=?, decided_at=NOW(), remarks=? WHERE id=?',
            [$data['status'], Auth::id(), $data['remarks'] ?? null, (int)$id]
        );
        $this->success('Leave request ' . $data['status'] . '.', '/hr/leave');
    }
}
