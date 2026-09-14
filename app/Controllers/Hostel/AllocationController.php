<?php
declare(strict_types=1);

namespace App\Controllers\Hostel;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\HostelAllocation;

final class AllocationController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('hostel.view');
        $allocations = Database::select(
            'SELECT ha.*, hr.room_number, hr.floor, h.name AS hostel_name,
                    s.admission_number, u.first_name, u.last_name
               FROM hostel_allocations ha
               JOIN hostel_rooms hr ON hr.id = ha.hostel_room_id
               JOIN hostels h       ON h.id  = hr.hostel_id
               JOIN students s      ON s.id  = ha.student_id
               JOIN users u         ON u.id  = s.user_id
              WHERE ha.status IN ("allocated","checked_in")
              ORDER BY h.name, hr.room_number'
        );
        return $this->view('hostel.allocations.index', ['pageTitle' => 'Room Allocations', 'allocations' => $allocations]);
    }

    public function create(Request $request): string
    {
        $this->authorize('hostel.allocate');
        return $this->view('hostel.allocations.create', [
            'pageTitle' => 'Allocate Room',
            'rooms'     => Database::select(
                "SELECT hr.id, CONCAT(h.name,' – Rm ',hr.room_number,' (',hr.room_type,')') AS label
                   FROM hostel_rooms hr JOIN hostels h ON h.id=hr.hostel_id
                  WHERE hr.status='available' ORDER BY h.name, hr.room_number"
            ),
            'students'  => Database::select(
                "SELECT s.id, CONCAT(s.admission_number,' – ',u.first_name,' ',u.last_name) AS label
                   FROM students s JOIN users u ON u.id=s.user_id
                  WHERE s.status='active'
                    AND s.id NOT IN (SELECT student_id FROM hostel_allocations WHERE status IN ('allocated','checked_in'))
                  ORDER BY u.last_name"
            ),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('hostel.allocate');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'student_id'      => 'required|integer|exists:students,id',
            'hostel_room_id'  => 'required|integer|exists:hostel_rooms,id',
            'semester_id'     => 'required|integer|exists:semesters,id',
            'bed_number'      => 'nullable|max:10',
        ]);
        $data['status']       = 'allocated';
        $data['allocated_by'] = Auth::id();
        (new HostelAllocation())->create($data);
        Database::statement("UPDATE hostel_rooms SET status='full'
             WHERE id=? AND capacity <= (SELECT COUNT(*) FROM hostel_allocations WHERE hostel_room_id=? AND status IN ('allocated','checked_in'))",
            [$data['hostel_room_id'], $data['hostel_room_id']]);
        $this->success('Room allocated.', '/hostel/allocations');
    }

    public function checkout(Request $request, string $id): never
    {
        $this->authorize('hostel.allocate');
        $this->verifyCsrf($request);
        $alloc = Database::selectOne('SELECT * FROM hostel_allocations WHERE id=?', [(int)$id]);
        if (!$alloc) { throw new HttpException(404); }

        Database::statement("UPDATE hostel_allocations SET status='checked_out', vacated_at=NOW() WHERE id=?", [(int)$id]);
        Database::statement("UPDATE hostel_rooms SET status='available' WHERE id=?", [$alloc['hostel_room_id']]);
        $this->success('Student checked out.', '/hostel/allocations');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('hostel.delete');
        $this->verifyCsrf($request);
        $alloc = Database::selectOne('SELECT * FROM hostel_allocations WHERE id=?', [(int)$id]);
        if ($alloc) { Database::statement("UPDATE hostel_rooms SET status='available' WHERE id=?", [$alloc['hostel_room_id']]); }
        (new HostelAllocation())->delete((int)$id);
        $this->success('Allocation removed.', '/hostel/allocations');
    }
}
