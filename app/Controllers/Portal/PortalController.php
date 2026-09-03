<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Announcement;
use App\Models\Student;
use App\Models\Semester;

final class PortalController extends Controller
{
    private Student $studentModel;

    public function __construct()
    {
        $this->studentModel = new Student();
    }

    private function getStudent(): array
    {
        $student = $this->studentModel->byUserId((int) Auth::id());
        if ($student === null) {
            throw new HttpException(403, 'No student profile linked to your account.');
        }
        return $student;
    }

    public function dashboard(Request $request): string
    {
        $student  = $this->getStudent();
        $semester = (new Semester())->current();

        return $this->view('portal.dashboard', [
            'pageTitle'    => 'My Portal',
            'student'      => $student,
            'semester'     => $semester,
            'registrations'=> $this->studentModel->registrations($student['id'], $semester['id'] ?? null),
            'balance'      => $this->studentModel->balance($student['id']),
            'attendance'   => $this->studentModel->attendanceRate($student['id'], $semester['id'] ?? null),
            'announcements'=> (new Announcement())->feedFor(Auth::user(), 5),
        ]);
    }

    public function profile(Request $request): string
    {
        $student = $this->getStudent();
        return $this->view('portal.profile', [
            'pageTitle' => 'My Profile',
            'student'   => $student,
        ]);
    }

    public function updateProfile(Request $request): never
    {
        $this->verifyCsrf($request);
        $student = $this->getStudent();

        $data = $this->validate($request, [
            'phone'                    => 'nullable|max:30',
            'alt_phone'                => 'nullable|max:30',
            'postal_address'           => 'nullable|max:150',
            'physical_address'         => 'nullable|max:255',
            'emergency_contact_name'   => 'nullable|max:150',
            'emergency_contact_phone'  => 'nullable|max:30',
            'emergency_contact_relation' => 'nullable|max:60',
        ]);

        // Updatable fields live on the users table
        $userFields = array_intersect_key($data, array_flip(['phone', 'alt_phone']));
        if ($userFields !== []) {
            \App\Core\Database::statement(
                'UPDATE users SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($userFields))) . ' WHERE id = ?',
                [...array_values($userFields), Auth::id()]
            );
        }
        // Student-specific fields
        $studentFields = array_diff_key($data, $userFields);
        if ($studentFields !== []) {
            $this->studentModel->update($student['id'], $studentFields);
        }

        $this->success('Profile updated.', '/portal/profile');
    }

    public function timetable(Request $request): string
    {
        $student  = $this->getStudent();
        $semester = (new Semester())->current();

        $slots = $semester ? \App\Core\Database::select(
            'SELECT ts.*, c.code, c.title, r.code AS room_code, r.name AS room_name,
                    CONCAT(lu.first_name, " ", lu.last_name) AS lecturer_name
               FROM timetable_slots ts
               JOIN course_offerings o ON o.id = ts.offering_id
               JOIN courses c          ON c.id = o.course_id
          LEFT JOIN rooms r            ON r.id = ts.room_id
          LEFT JOIN staff lst          ON lst.id = o.lecturer_id
          LEFT JOIN users lu           ON lu.id = lst.user_id
               JOIN course_registrations cr ON cr.offering_id = o.id AND cr.student_id = ?
              WHERE ts.semester_id = ? AND cr.status = "registered"
              ORDER BY FIELD(ts.day_of_week,"monday","tuesday","wednesday","thursday","friday","saturday","sunday"), ts.start_time',
            [$student['id'], $semester['id']]
        ) : [];

        return $this->view('portal.timetable', [
            'pageTitle' => 'My Timetable',
            'student'   => $student,
            'semester'  => $semester,
            'slots'     => $slots,
        ]);
    }

    public function attendance(Request $request): string
    {
        $student  = $this->getStudent();
        $semester = (new Semester())->current();

        $records = $semester ? \App\Core\Database::select(
            'SELECT c.code, c.title,
                    COUNT(ar.id) AS total,
                    SUM(CASE WHEN ar.status IN ("present","late") THEN 1 ELSE 0 END) AS attended
               FROM attendance_records ar
               JOIN attendance_sessions s ON s.id = ar.session_id
               JOIN course_offerings o    ON o.id = s.offering_id
               JOIN courses c             ON c.id = o.course_id
              WHERE ar.student_id = ? AND o.semester_id = ?
              GROUP BY o.id, c.code, c.title
              ORDER BY c.code',
            [$student['id'], $semester['id']]
        ) : [];

        return $this->view('portal.attendance', [
            'pageTitle' => 'Attendance',
            'student'   => $student,
            'semester'  => $semester,
            'records'   => $records,
        ]);
    }

    public function library(Request $request): string
    {
        $student = $this->getStudent();
        $loans   = \App\Core\Database::select(
            'SELECT bl.*, b.title, b.author, b.accession_number
               FROM book_loans bl
               JOIN books b ON b.id = bl.book_id
              WHERE bl.student_id = ?
              ORDER BY bl.issued_date DESC',
            [$student['id']]
        );

        return $this->view('portal.library', [
            'pageTitle' => 'Library',
            'student'   => $student,
            'loans'     => $loans,
        ]);
    }

    public function hostel(Request $request): string
    {
        $student    = $this->getStudent();
        $allocation = \App\Core\Database::selectOne(
            'SELECT ha.*, hr.room_number, hr.floor, h.name AS hostel_name
               FROM hostel_allocations ha
               JOIN hostel_rooms hr ON hr.id = ha.room_id
               JOIN hostels h       ON h.id = hr.hostel_id
              WHERE ha.student_id = ? AND ha.status = "active"
              LIMIT 1',
            [$student['id']]
        );

        return $this->view('portal.hostel', [
            'pageTitle'  => 'Accommodation',
            'student'    => $student,
            'allocation' => $allocation,
        ]);
    }

    public function clearance(Request $request): string
    {
        $student = $this->getStudent();
        $items   = \App\Core\Database::select(
            'SELECT * FROM clearances WHERE student_id = ? ORDER BY department',
            [$student['id']]
        );

        return $this->view('portal.clearance', [
            'pageTitle' => 'Clearance Status',
            'student'   => $student,
            'items'     => $items,
        ]);
    }
}
