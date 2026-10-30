<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Announcement;
use App\Models\Payment;
use App\Models\Semester;
use App\Services\BillingService;

final class DashboardController extends Controller
{
    public function index(Request $request): string
    {
        $user = Auth::user();

        // Students and lecturers get their own workspaces.
        if ($user['user_type'] === 'student' && !Auth::can('students.view')) {
            Response::redirect('/portal');
        }
        if ($user['user_type'] === 'lecturer' && !Auth::can('students.view')) {
            Response::redirect('/teaching');
        }

        $semester = (new Semester())->current();
        $stats    = $this->headlineStats();
        $finance  = Auth::can('finance.view') ? (new BillingService())->summary() : null;
        $people   = Auth::can('students.view');

        return $this->view('dashboard.index', [
            'pageTitle'     => 'Dashboard',
            'semester'      => $semester,
            'calendar'      => $semester !== null ? $this->semesterCalendar($semester) : null,
            'stats'         => $stats,
            'finance'       => $finance,
            'registration'  => $semester !== null && $people ? $this->registrationStatus((int) $semester['id'], $stats['students']) : null,
            'colleges'      => $people ? $this->enrolmentByCollege() : [],
            'standing'      => $people ? $this->classStanding() : [],
            'gender'        => $people ? $this->studentGender() : [],
            'staffMix'      => Auth::can('staff.view') ? $this->staffMix() : [],
            'trend'         => $finance !== null ? (new Payment())->monthlyTrend(12) : [],
            'recentPayments'=> $finance !== null ? $this->recentPayments() : [],
            'applications'  => Auth::can('admissions.view') ? $this->applicationFunnel() : [],
            'announcements' => (new Announcement())->feedFor($user, 5),
            'events'        => $this->upcomingEvents(),
            'attention'     => $this->needsAttention(),
            'actions'       => $this->quickActions(),
        ]);
    }

    private function headlineStats(): array
    {
        return [
            'students'   => (int) Database::scalar("SELECT COUNT(*) FROM students WHERE status = 'active'"),
            'staff'      => (int) Database::scalar("SELECT COUNT(*) FROM staff WHERE status = 'active'"),
            'academic'   => (int) Database::scalar("SELECT COUNT(*) FROM staff WHERE status = 'active' AND staff_category = 'academic'"),
            'colleges'   => (int) Database::scalar("SELECT COUNT(*) FROM faculties WHERE status = 'active'"),
            'departments'=> (int) Database::scalar("SELECT COUNT(*) FROM departments WHERE status = 'active'"),
            'programs'   => (int) Database::scalar("SELECT COUNT(*) FROM programs WHERE status = 'active'"),
            'courses'    => (int) Database::scalar("SELECT COUNT(*) FROM courses WHERE status = 'active'"),
            'offerings'  => (int) Database::scalar(
                "SELECT COUNT(*) FROM course_offerings o
                   JOIN semesters s ON s.id = o.semester_id AND s.is_current = 1"
            ),
            'applicants' => (int) Database::scalar(
                "SELECT COUNT(*) FROM applications WHERE status IN ('submitted','under_review','shortlisted')"
            ),
            'books'      => (int) Database::scalar('SELECT COALESCE(SUM(total_copies), 0) FROM books'),
            'hostel_beds'=> (int) Database::scalar('SELECT COALESCE(SUM(capacity), 0) FROM hostel_rooms'),
        ];
    }

    /** Where today falls in the semester: week, progress, and the registration / examination windows. */
    private function semesterCalendar(array $semester): array
    {
        $today = strtotime(date('Y-m-d'));
        $start = strtotime((string) $semester['start_date']);
        $end   = strtotime((string) $semester['end_date']);
        $span  = max(1, $end - $start);
        $weeks = (int) ceil(($span + 86400) / 604800);
        $in    = static fn (?string $from, ?string $to): bool => $from !== null && $to !== null
            && $today >= strtotime($from) && $today <= strtotime($to);
        $registrationOpen = $in($semester['registration_start'] ?? null, $semester['registration_end'] ?? null);

        return [
            'phase'    => match (true) {
                $today < $start => 'Not yet started',
                $today > $end   => 'Semester ended',
                $in($semester['exam_start'] ?? null, $semester['exam_end'] ?? null) => 'Examinations',
                $registrationOpen => 'Registration open',
                default         => 'Teaching in progress',
            },
            'progress' => (int) round(min(1, max(0, ($today - $start) / $span)) * 100),
            'week'     => $today < $start ? 0 : min($weeks, intdiv($today - $start, 604800) + 1),
            'weeks'    => $weeks,
            'daysLeft' => max(0, (int) round(($end - $today) / 86400)),
            'registrationOpen' => $registrationOpen,
        ];
    }

    /** Current-semester registration: enrolled (1st installment paid), awaiting payment, and not yet started. */
    private function registrationStatus(int $semesterId, int $activeStudents): array
    {
        $row = Database::selectOne(
            "SELECT COALESCE(SUM(se.status = 'enrolled'), 0)  AS enrolled,
                    COALESCE(SUM(se.status <> 'enrolled'), 0) AS pending,
                    COALESCE(SUM(se.registration_stage = 'planned'), 0)       AS planned,
                    COALESCE(SUM(se.registration_stage = 'committed'), 0)     AS committed,
                    COALESCE(SUM(se.registration_stage = 'not_committed'), 0) AS not_committed
               FROM semester_enrollments se
               JOIN students s ON s.id = se.student_id AND s.status = 'active'
              WHERE se.semester_id = ?",
            [$semesterId]
        ) ?? [];
        $enrolled = (int) $row['enrolled'];
        $pending  = (int) $row['pending'];
        return [
            'enrolled'      => $enrolled,
            'pending'       => $pending,
            'not_started'   => max(0, $activeStudents - $enrolled - $pending),
            'total'         => $activeStudents,
            'rate'          => $activeStudents > 0 ? round($enrolled / $activeStudents * 100, 1) : 0.0,
            'planned'       => (int) $row['planned'],
            'committed'     => (int) $row['committed'],
            'not_committed' => (int) $row['not_committed'],
        ];
    }

    private function enrolmentByCollege(): array
    {
        return Database::select(
            "SELECT f.code, f.name, COUNT(s.id) AS total, COUNT(DISTINCT p.id) AS programs
               FROM faculties f
          LEFT JOIN departments d ON d.faculty_id = f.id
          LEFT JOIN programs p    ON p.department_id = d.id AND p.status = 'active'
          LEFT JOIN students s    ON s.program_id = p.id AND s.status = 'active'
              WHERE f.status = 'active'
              GROUP BY f.id
              ORDER BY total DESC, f.name"
        );
    }

    /** Active students by year of study (Freshman ... Senior). */
    private function classStanding(): array
    {
        $rows  = array_column(Database::select(
            "SELECT LEAST(GREATEST(year_of_study, 1), 4) AS yr, COUNT(*) AS total
               FROM students WHERE status = 'active' GROUP BY yr"
        ), 'total', 'yr');
        $out = [];
        foreach ([1 => 'Freshman', 2 => 'Sophomore', 3 => 'Junior', 4 => 'Senior'] as $year => $name) {
            $out[] = ['label' => $name, 'note' => 'Year ' . $year . ($year === 4 ? '+' : ''), 'total' => (int) ($rows[$year] ?? 0)];
        }
        return $out;
    }

    private function studentGender(): array
    {
        return array_map('intval', array_column(Database::select(
            "SELECT COALESCE(u.gender, 'unspecified') AS gender, COUNT(*) AS total
               FROM students s JOIN users u ON u.id = s.user_id
              WHERE s.status = 'active' GROUP BY gender"
        ), 'total', 'gender'));
    }

    private function staffMix(): array
    {
        return array_map('intval', array_column(Database::select(
            "SELECT staff_category, COUNT(*) AS total FROM staff WHERE status = 'active'
              GROUP BY staff_category ORDER BY total DESC"
        ), 'total', 'staff_category'));
    }

    /** Shortcuts to the everyday tasks this person is allowed to do. */
    private function quickActions(): array
    {
        $all = [
            ['label' => 'Admit a student',      'url' => '/students/create',                'icon' => 'student',   'permission' => 'students.create'],
            ['label' => 'Record a payment',     'url' => '/finance/payments/create',        'icon' => 'money',     'permission' => 'finance.create'],
            ['label' => 'Register courses',     'url' => '/academics/registrations/create', 'icon' => 'clipboard', 'permission' => 'registrations.create'],
            ['label' => 'Generate invoices',    'url' => '/finance/invoices/generate',      'icon' => 'file',      'permission' => 'finance.create'],
            ['label' => 'Review applications',  'url' => '/admissions/applications',        'icon' => 'check',     'permission' => 'admissions.view'],
            ['label' => 'Post an announcement', 'url' => '/announcements/create',           'icon' => 'bell',      'permission' => 'announcements.create'],
            ['label' => 'Print login slips',    'url' => '/admin/data-imports/slips',       'icon' => 'print',     'permission' => 'users.reset'],
            ['label' => 'Reports',              'url' => '/reports',                        'icon' => 'chart',     'permission' => 'reports.view'],
        ];
        return array_values(array_filter($all, static fn (array $a): bool => Auth::can($a['permission'])));
    }

    private function recentPayments(): array
    {
        return Database::select(
            "SELECT p.receipt_number, p.amount, p.method, p.paid_at,
                    s.admission_number, u.first_name, u.last_name
               FROM payments p
               JOIN students s ON s.id = p.student_id
               JOIN users u    ON u.id = s.user_id
              WHERE p.status = 'confirmed'
              ORDER BY p.paid_at DESC
              LIMIT 6"
        );
    }

    private function applicationFunnel(): array
    {
        $rows = Database::select('SELECT status, COUNT(*) AS total FROM applications GROUP BY status');
        $out  = [];
        foreach ($rows as $row) {
            $out[$row['status']] = (int) $row['total'];
        }
        return $out;
    }

    private function upcomingEvents(): array
    {
        return Database::select(
            'SELECT * FROM events WHERE start_datetime >= NOW() ORDER BY start_datetime LIMIT 5'
        );
    }

    /** Items that need someone to act on them. */
    private function needsAttention(): array
    {
        $items = [];

        if (Auth::can('registrations.view')) {
            $pending = (int) Database::scalar(
                "SELECT COUNT(*) FROM course_registrations WHERE approval_status = 'pending'"
            );
            if ($pending > 0) {
                $items[] = [
                    'label' => $pending . ' course registration(s) awaiting approval',
                    'url'   => '/academics/registrations?approval_status=pending',
                    'tone'  => 'warning',
                ];
            }
        }
        if (Auth::can('admissions.view')) {
            $apps = (int) Database::scalar("SELECT COUNT(*) FROM applications WHERE status = 'submitted'");
            if ($apps > 0) {
                $items[] = [
                    'label' => $apps . ' new application(s) to review',
                    'url'   => '/admissions/applications?status=submitted',
                    'tone'  => 'info',
                ];
            }
        }
        if (Auth::can('finance.view')) {
            $overdue = (int) Database::scalar("SELECT COUNT(*) FROM invoices WHERE status = 'overdue'");
            if ($overdue > 0) {
                $items[] = [
                    'label' => $overdue . ' overdue invoice(s)',
                    'url'   => '/finance/invoices?status=overdue',
                    'tone'  => 'danger',
                ];
            }
        }
        if (Auth::can('library.view')) {
            $late = (int) Database::scalar(
                "SELECT COUNT(*) FROM book_loans WHERE status IN ('borrowed','overdue') AND due_date < CURDATE()"
            );
            if ($late > 0) {
                $items[] = [
                    'label' => $late . ' library book(s) overdue',
                    'url'   => '/library/loans?status=overdue',
                    'tone'  => 'warning',
                ];
            }
        }
        if (Auth::can('hr.view')) {
            $leave = (int) Database::scalar("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
            if ($leave > 0) {
                $items[] = [
                    'label' => $leave . ' leave request(s) pending',
                    'url'   => '/hr/leave?status=pending',
                    'tone'  => 'info',
                ];
            }
        }
        return $items;
    }

    /** Global quick search across the main entities. */
    public function search(Request $request): string
    {
        $term = trim((string) $request->query('q', ''));
        $results = ['students' => [], 'staff' => [], 'courses' => [], 'programs' => [], 'books' => []];

        if ($term !== '') {
            $like = '%' . $term . '%';

            if (Auth::can('students.view')) {
                $results['students'] = Database::select(
                    'SELECT s.id, s.admission_number, u.first_name, u.last_name, p.name AS program_name
                       FROM students s
                       JOIN users u    ON u.id = s.user_id
                       JOIN programs p ON p.id = s.program_id
                      WHERE s.admission_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?
                      LIMIT 10',
                    [$like, $like, $like, $like]
                );
            }
            if (Auth::can('staff.view')) {
                $results['staff'] = Database::select(
                    'SELECT st.id, st.staff_number, st.designation, u.first_name, u.last_name
                       FROM staff st JOIN users u ON u.id = st.user_id
                      WHERE st.staff_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
                      LIMIT 10',
                    [$like, $like, $like]
                );
            }
            if (Auth::can('academics.view')) {
                $results['courses'] = Database::select(
                    'SELECT id, code, title, credit_hours FROM courses WHERE code LIKE ? OR title LIKE ? LIMIT 10',
                    [$like, $like]
                );
                $results['programs'] = Database::select(
                    'SELECT id, code, name, level FROM programs WHERE code LIKE ? OR name LIKE ? LIMIT 10',
                    [$like, $like]
                );
            }
            if (Auth::can('library.view')) {
                $results['books'] = Database::select(
                    'SELECT id, accession_number, title, author FROM books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? LIMIT 10',
                    [$like, $like, $like]
                );
            }
        }

        return $this->view('dashboard.search', [
            'pageTitle' => 'Search results',
            'term'      => $term,
            'results'   => $results,
        ]);
    }
}
