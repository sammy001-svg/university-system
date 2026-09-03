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

        return $this->view('dashboard.index', [
            'pageTitle'     => 'Dashboard',
            'semester'      => $semester,
            'stats'         => $this->headlineStats(),
            'finance'       => Auth::can('finance.view') ? (new BillingService())->summary() : null,
            'enrolment'     => $this->enrolmentByProgram(),
            'trend'         => Auth::can('finance.view') ? (new Payment())->monthlyTrend(9) : [],
            'recentPayments'=> Auth::can('finance.view') ? $this->recentPayments() : [],
            'applications'  => Auth::can('admissions.view') ? $this->applicationFunnel() : [],
            'announcements' => (new Announcement())->feedFor($user, 5),
            'events'        => $this->upcomingEvents(),
            'attention'     => $this->needsAttention(),
        ]);
    }

    private function headlineStats(): array
    {
        return [
            'students'   => (int) Database::scalar("SELECT COUNT(*) FROM students WHERE status = 'active'"),
            'staff'      => (int) Database::scalar("SELECT COUNT(*) FROM staff WHERE status = 'active'"),
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

    private function enrolmentByProgram(): array
    {
        return Database::select(
            "SELECT p.code, p.name, COUNT(s.id) AS total
               FROM programs p
          LEFT JOIN students s ON s.program_id = p.id AND s.status = 'active'
              WHERE p.status = 'active'
              GROUP BY p.id
              ORDER BY total DESC
              LIMIT 8"
        );
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
