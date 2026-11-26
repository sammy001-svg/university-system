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
use App\Models\SemesterEnrollment;
use App\Services\Elearning\ClassroomService;
use App\Services\Elearning\CourseAccess;
use App\Services\Elearning\ExamService;

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
        $student    = $this->getStudent();
        $semester   = (new Semester())->current();
        $studentId  = (int) $student['id'];
        $semesterId = isset($semester['id']) ? (int) $semester['id'] : null;
        $offeringIds = CourseAccess::studiedOfferingIds($studentId, $semesterId);

        // Online classes that are live now or start within the next 7 days.
        $classes = [];
        foreach (ClassroomService::sessions($offeringIds, $studentId) as $session) {
            $phase = ClassroomService::phase($session);
            if ($phase === 'live' || ($phase === 'upcoming' && strtotime($session['starts_at']) < strtotime('+7 days'))) {
                $classes[] = $session + ['phase' => $phase, 'can_join' => ClassroomService::canJoin($session)];
            }
        }

        // Exams open now or opening soon that the student has not finished.
        $exams = [];
        foreach (\App\Core\Database::select(
            'SELECT e.id, c.code FROM online_exams e JOIN course_offerings o ON o.id = e.offering_id JOIN courses c ON c.id = o.course_id
              WHERE e.offering_id IN (' . CourseAccess::inList($offeringIds) . ') AND e.status = "published" ORDER BY e.opens_at'
        ) as $row) {
            $exam    = ExamService::find((int) $row['id']) + ['code' => $row['code']];
            $phase   = ExamService::phase($exam);
            $attempt = ExamService::attempt((int) $exam['id'], $studentId);
            if (in_array($phase, ['open', 'upcoming'], true) && ($attempt === null || $attempt['status'] === 'in_progress')) {
                $exams[] = $exam + ['phase' => $phase, 'attempt' => $attempt];
            }
        }

        $gpa = \App\Core\Database::selectOne(
            'SELECT SUM(quality_points) AS qp, SUM(credit_hours) AS credits,
                    SUM(CASE WHEN outcome = "pass" THEN credit_hours ELSE 0 END) AS earned
               FROM course_results WHERE student_id = ? AND is_published = 1',
            [$studentId]
        );
        $registrations = $this->studentModel->registrations($studentId, $semesterId);
        $enrollment    = $semesterId !== null ? (new SemesterEnrollment())->forStudent($studentId, $semesterId) : null;

        // What the student still has to do this semester.
        $todo = [];
        if ($semesterId !== null && $registrations === []) {
            $todo[] = ['label' => 'Register your courses for ' . $semester['name'], 'url' => '/portal/registration', 'tone' => 'warning'];
        }
        if ($enrollment !== null && $enrollment['status'] !== 'enrolled') {
            $todo[] = ['label' => 'Pay your 1st installment (' . money($enrollment['first_outstanding']) . ') to complete registration',
                       'url' => '/portal/finance/invoice/' . $enrollment['invoice_id'], 'tone' => 'danger'];
        }
        foreach ($exams as $exam) {
            if ($exam['phase'] === 'open') {
                $todo[] = ['label' => ($exam['attempt'] ? 'Finish' : 'Sit') . ' ' . $exam['code'] . ': ' . $exam['title'] . ' (closes ' . fdatetime($exam['closes_at']) . ')',
                           'url' => '/portal/exams/' . $exam['id'], 'tone' => 'warning'];
            }
        }
        foreach (\App\Core\Database::select(
            'SELECT a.id, a.title, a.due_at, c.code FROM assignments a JOIN course_offerings o ON o.id = a.offering_id JOIN courses c ON c.id = o.course_id
              WHERE a.offering_id IN (' . CourseAccess::inList($offeringIds) . ') AND a.status = "published" AND a.due_at BETWEEN ? AND ?
                AND NOT EXISTS (SELECT 1 FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ?)
              ORDER BY a.due_at',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s', strtotime('+7 days')), $studentId]
        ) as $a) {
            $todo[] = ['label' => 'Hand in ' . $a['code'] . ': ' . $a['title'] . ' (due ' . fdatetime($a['due_at']) . ')',
                       'url' => '/portal/assignments/' . $a['id'], 'tone' => 'warning'];
        }
        if (empty($student['phone']) || empty($student['emergency_contact_name'])) {
            $todo[] = ['label' => 'Complete your contact and emergency details', 'url' => '/portal/profile', 'tone' => 'info'];
        }

        return $this->view('portal.dashboard', [
            'pageTitle'     => 'My Portal',
            'student'       => $student,
            'semester'      => $semester,
            'registrations' => $registrations,
            'credits'       => array_sum(array_map(static fn (array $r): int => (int) ($r['credit_hours'] ?? 0), $registrations)),
            'balance'       => $this->studentModel->balance($studentId),
            'attendance'    => $this->studentModel->attendanceRate($studentId, $semesterId),
            'cgpa'          => (float) ($gpa['credits'] ?? 0) > 0 ? round((float) $gpa['qp'] / (float) $gpa['credits'], 2) : null,
            'creditsEarned' => (int) ($gpa['earned'] ?? 0),
            'classes'       => $classes,
            'exams'         => $exams,
            'todo'          => $todo,
            'announcements' => (new Announcement())->feedFor(Auth::user(), 5),
            'enrollment'    => $enrollment,
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
               JOIN students s ON s.user_id = bl.user_id
              WHERE s.id = ?
              ORDER BY bl.returned_at IS NOT NULL, bl.due_date',
            [$student['id']]
        );

        // The catalogue, searchable but read-only: the staff circulation desk
        // lists every borrower, so students never see that page.
        $search  = trim((string) $request->query('q', ''));
        $results = [];
        if ($search !== '') {
            $like    = '%' . $search . '%';
            $results = \App\Core\Database::select(
                'SELECT b.id, b.accession_number, b.title, b.author, b.publisher, b.publication_year,
                        b.shelf_location, b.total_copies, b.available_copies, c.name AS category
                   FROM books b
              LEFT JOIN book_categories c ON c.id = b.category_id
                  WHERE b.status = "available"
                    AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.accession_number LIKE ?)
                  ORDER BY b.title
                  LIMIT 50',
                [$like, $like, $like, $like]
            );
        }

        $open = array_filter($loans, static fn (array $l): bool => $l['returned_at'] === null);

        return $this->view('portal.library', [
            'pageTitle' => 'Library',
            'student'   => $student,
            'loans'     => $loans,
            'search'    => $search,
            'results'   => $results,
            'summary'   => [
                'on_loan' => count($open),
                'overdue' => count(array_filter($open, static fn (array $l): bool =>
                    $l['due_date'] !== null && strtotime((string) $l['due_date']) < strtotime(date('Y-m-d')))),
                'fines'   => array_sum(array_map(
                    static fn (array $l): float => (int) $l['fine_paid'] === 1 ? 0.0 : (float) $l['fine_amount'],
                    $loans
                )),
            ],
        ]);
    }

    public function hostel(Request $request): string
    {
        $student    = $this->getStudent();
        $allocation = \App\Core\Database::selectOne(
            'SELECT ha.*, hr.room_number, hr.floor, h.name AS hostel_name
               FROM hostel_allocations ha
               JOIN hostel_rooms hr ON hr.id = ha.hostel_room_id
               JOIN hostels h       ON h.id = hr.hostel_id
              WHERE ha.student_id = ? AND ha.status IN ("allocated","checked_in")
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
            'SELECT * FROM clearances WHERE student_id = ? ORDER BY unit',
            [$student['id']]
        );

        return $this->view('portal.clearance', [
            'pageTitle' => 'Clearance Status',
            'student'   => $student,
            'items'     => $items,
        ]);
    }
}
