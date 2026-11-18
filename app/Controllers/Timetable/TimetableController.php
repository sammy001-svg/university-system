<?php
declare(strict_types=1);

namespace App\Controllers\Timetable;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\TimetableSlot;
use App\Services\Academic\ScheduleConflict;

final class TimetableController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('timetable.view');
        $semesterId = $request->query('semester_id');
        $semesters  = Database::select(
            'SELECT s.id, CONCAT(ay.name, " ", s.name) AS name, s.is_current FROM semesters s
               JOIN academic_years ay ON ay.id = s.academic_year_id ORDER BY ay.start_date DESC, s.semester_number DESC'
        );
        $current = $semesterId
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
            'clashes'   => $current ? count(ScheduleConflict::auditSemester((int) $current['id'])) : 0,
        ]);
    }

    /** Clashes already present in a semester's timetable. */
    public function conflicts(Request $request): string
    {
        $this->authorize('timetable.view');
        $semesterId = (int) ($request->query('semester_id')
            ?: Database::scalar('SELECT id FROM semesters WHERE is_current = 1 LIMIT 1'));
        return $this->view('timetable.conflicts', [
            'pageTitle' => 'Timetable Conflicts',
            'semesters' => Database::select(
                'SELECT s.id, CONCAT(ay.name, " ", s.name) AS name FROM semesters s
                   JOIN academic_years ay ON ay.id = s.academic_year_id ORDER BY ay.start_date DESC, s.semester_number DESC'
            ),
            'semesterId' => $semesterId,
            'conflicts'  => $semesterId ? ScheduleConflict::auditSemester($semesterId) : [],
        ]);
    }

    public function create(Request $request): string
    {
        $this->authorize('timetable.create');
        return $this->view('timetable.form', $this->formData([
            'pageTitle' => 'Add a timetable slot',
            'isNew'     => true,
            'slot'      => ['offering_id' => (int) $request->query('offering_id', 0), 'day_of_week' => 'monday',
                            'start_time' => '08:00', 'end_time' => '10:00', 'room_id' => '', 'session_type' => 'lecture'],
        ]));
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('timetable.edit');
        return $this->view('timetable.form', $this->formData([
            'pageTitle' => 'Move a timetable slot',
            'isNew'     => false,
            'slot'      => $this->slot((int) $id),
        ]));
    }

    public function store(Request $request): never
    {
        $this->authorize('timetable.create');
        $this->verifyCsrf($request);
        $data = $this->slotData($request);

        $check = ScheduleConflict::forSlot($data);
        if ($check['errors'] !== []) {
            $this->reject($check['errors'], '/timetable/create');
        }
        $id = (new TimetableSlot())->create($data);
        Audit::log('create', 'timetable', 'timetable_slots', (string) $id, 'Timetable slot added');
        $this->success('Timetable slot added.' . self::note($check['warnings']), '/timetable?semester_id=' . $data['semester_id']);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('timetable.edit');
        $this->verifyCsrf($request);
        $this->slot((int) $id);
        $data = $this->slotData($request);

        $check = ScheduleConflict::forSlot($data, (int) $id);
        if ($check['errors'] !== []) {
            $this->reject($check['errors'], '/timetable/' . $id . '/edit');
        }
        (new TimetableSlot())->update((int) $id, $data);
        Audit::log('update', 'timetable', 'timetable_slots', $id, 'Timetable slot moved');
        $this->success('Timetable slot updated.' . self::note($check['warnings']), '/timetable?semester_id=' . $data['semester_id']);
    }

    /** Live check used by the slot form; returns the clashes without saving anything. */
    public function check(Request $request): never
    {
        $this->authorize('timetable.create');
        $offering = Database::selectOne('SELECT semester_id FROM course_offerings WHERE id = ?', [(int) $request->input('offering_id')]);
        if ($offering === null) {
            Response::json(['ok' => false, 'errors' => [], 'warnings' => []]);
        }
        $check = ScheduleConflict::forSlot([
            'offering_id' => (int) $request->input('offering_id'),
            'semester_id' => (int) $offering['semester_id'],
            'day_of_week' => (string) $request->input('day_of_week'),
            'start_time'  => (string) $request->input('start_time'),
            'end_time'    => (string) $request->input('end_time'),
            'room_id'     => $request->input('room_id') ?: null,
        ], $request->filled('slot_id') ? (int) $request->input('slot_id') : null);

        Response::json($check + ['ok' => $check['errors'] === []]);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('timetable.delete');
        $this->verifyCsrf($request);
        (new TimetableSlot())->delete((int) $id);
        Audit::log('delete', 'timetable', 'timetable_slots', $id, 'Timetable slot removed');
        $this->success('Slot removed.', '/timetable');
    }

    /* ------------------------------------------------------------------ */

    private function slot(int $id): array
    {
        $slot = Database::selectOne(
            'SELECT ts.*, c.code, c.title, o.section FROM timetable_slots ts
               JOIN course_offerings o ON o.id = ts.offering_id JOIN courses c ON c.id = o.course_id WHERE ts.id = ?',
            [$id]
        );
        if ($slot === null) {
            throw new HttpException(404, 'Timetable slot not found.');
        }
        return $slot;
    }

    /** The posted slot, with the semester taken from the class so the two can never disagree. */
    private function slotData(Request $request): array
    {
        $data = $this->validate($request, [
            'offering_id'  => 'required|integer|exists:course_offerings,id',
            'day_of_week'  => 'required|in:' . implode(',', ScheduleConflict::DAYS),
            'start_time'   => 'required',
            'end_time'     => 'required',
            'room_id'      => 'nullable|integer|exists:rooms,id',
            'session_type' => 'required|in:lecture,tutorial,practical,seminar',
        ], ['offering_id' => 'Class', 'day_of_week' => 'Day', 'room_id' => 'Room']);

        $data['semester_id'] = (int) Database::scalar('SELECT semester_id FROM course_offerings WHERE id = ?', [$data['offering_id']]);
        $data['start_time']  = date('H:i:s', strtotime((string) $data['start_time']) ?: 0);
        $data['end_time']    = date('H:i:s', strtotime((string) $data['end_time']) ?: 0);
        $data['room_id']     = ($data['room_id'] ?? '') ?: null;
        return $data;
    }

    private function formData(array $extra): array
    {
        return $extra + [
            'offerings' => Database::select(
                'SELECT o.id, CONCAT(c.code, " ", c.title, " - section ", o.section) AS label, CONCAT(ay.name, " ", sem.name) AS semester_name
                   FROM course_offerings o
                   JOIN courses c       ON c.id = o.course_id
                   JOIN semesters sem   ON sem.id = o.semester_id
                   JOIN academic_years ay ON ay.id = sem.academic_year_id
                  WHERE sem.is_current = 1 OR o.id = ?
                  ORDER BY c.code, o.section',
                [(int) ($extra['slot']['offering_id'] ?? 0)]
            ),
            'rooms' => Database::select(
                "SELECT id, CONCAT(code, ' - ', name, ' (', capacity, ' seats)') AS label, status FROM rooms ORDER BY code"
            ),
        ];
    }

    /** Refuse the slot, keeping what was typed and listing every clash. */
    private function reject(array $errors, string $back): never
    {
        Session::keepOldInput($_POST);
        Session::flash('clashes', $errors);
        $this->error(count($errors) === 1 ? $errors[0] : 'This class cannot be scheduled there - ' . count($errors) . ' clashes, listed below.', $back);
    }

    private static function note(array $warnings): string
    {
        return $warnings === [] ? '' : ' Please note: ' . implode(' ', $warnings);
    }
}
