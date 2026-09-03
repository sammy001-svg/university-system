<?php
declare(strict_types=1);

namespace App\Controllers\Timetable;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Models\TimetableSlot;

final class TimetableController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('timetable.view');
        $semesterId = $request->query('semester_id');
        $semesters  = Database::select('SELECT * FROM semesters ORDER BY id DESC');
        $current    = $semesterId
            ? Database::selectOne('SELECT * FROM semesters WHERE id=?', [(int)$semesterId])
            : Database::selectOne('SELECT * FROM semesters WHERE is_current=1 LIMIT 1');

        $slots = $current ? Database::select(
            'SELECT ts.*, c.code, c.title, o.section, r.code AS room_code, r.name AS room_name,
                    CONCAT(lu.first_name," ",lu.last_name) AS lecturer_name
               FROM timetable_slots ts
               JOIN course_offerings o ON o.id = ts.offering_id
               JOIN courses c          ON c.id = o.course_id
          LEFT JOIN rooms r            ON r.id = ts.room_id
          LEFT JOIN staff lst          ON lst.id = o.lecturer_id
          LEFT JOIN users lu           ON lu.id = lst.user_id
              WHERE ts.semester_id = ?
              ORDER BY FIELD(ts.day_of_week,"monday","tuesday","wednesday","thursday","friday","saturday","sunday"), ts.start_time',
            [$current['id']]
        ) : [];

        return $this->view('timetable.index', [
            'pageTitle' => 'Timetable',
            'semesters' => $semesters,
            'current'   => $current,
            'slots'     => $slots,
        ]);
    }

    public function create(Request $request): string
    {
        $this->authorize('timetable.create');
        return $this->view('timetable.create', [
            'pageTitle' => 'Add Timetable Slot',
            'offerings' => Database::select(
                'SELECT o.id, c.code, c.title, o.section, sem.name AS semester_name
                   FROM course_offerings o
                   JOIN courses c     ON c.id = o.course_id
                   JOIN semesters sem ON sem.id = o.semester_id
                  WHERE sem.is_current=1 ORDER BY c.code'
            ),
            'rooms'     => Database::select("SELECT id, CONCAT(code,' – ',name) AS label FROM rooms WHERE status='available' ORDER BY code"),
            'semesters' => Database::select('SELECT id, name FROM semesters ORDER BY id DESC'),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('timetable.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'offering_id'  => 'required|integer|exists:course_offerings,id',
            'semester_id'  => 'required|integer|exists:semesters,id',
            'day_of_week'  => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time'   => 'required',
            'end_time'     => 'required',
            'room_id'      => 'nullable|integer|exists:rooms,id',
            'session_type' => 'required|in:lecture,tutorial,practical,seminar',
        ]);
        (new TimetableSlot())->create($data);
        $this->success('Timetable slot added.', '/timetable');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('timetable.delete');
        $this->verifyCsrf($request);
        (new TimetableSlot())->delete((int) $id);
        $this->success('Slot removed.', '/timetable');
    }
}
