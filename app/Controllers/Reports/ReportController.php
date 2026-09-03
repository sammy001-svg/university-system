<?php
declare(strict_types=1);

namespace App\Controllers\Reports;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;

final class ReportController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('reports.view');
        return $this->view('reports.index', ['pageTitle' => 'Reports']);
    }

    public function enrolment(Request $request): string
    {
        $this->authorize('reports.view');
        $data = Database::select(
            'SELECT p.name AS program, p.level, f.name AS faculty,
                    COUNT(s.id) AS total,
                    SUM(CASE WHEN s.status="active" THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN s.gender="male"   THEN 1 ELSE 0 END) AS male,
                    SUM(CASE WHEN s.gender="female" THEN 1 ELSE 0 END) AS female
               FROM students s
               JOIN programs p    ON p.id = s.program_id
               JOIN departments d ON d.id = p.department_id
               JOIN faculties f   ON f.id = d.faculty_id
              GROUP BY p.id
              ORDER BY f.name, p.name'
        );
        return $this->view('reports.enrolment', ['pageTitle' => 'Enrolment Report', 'data' => $data]);
    }

    public function finance(Request $request): string
    {
        $this->authorize('reports.view');
        $summary = Database::selectOne(
            'SELECT
               COALESCE(SUM(i.total_amount - i.discount_amount), 0) AS total_billed,
               COALESCE(SUM(i.amount_paid), 0) AS total_collected,
               COALESCE(SUM(i.balance), 0) AS outstanding,
               COUNT(DISTINCT i.student_id) AS student_count
             FROM invoices i WHERE i.status NOT IN ("cancelled","draft")'
        );
        $monthly = Database::select(
            'SELECT DATE_FORMAT(paid_at, "%Y-%m") AS month, SUM(amount) AS total
               FROM payments WHERE status="confirmed" GROUP BY month ORDER BY month DESC LIMIT 12'
        );
        return $this->view('reports.finance', ['pageTitle' => 'Finance Report', 'summary' => $summary, 'monthly' => $monthly]);
    }

    public function academic(Request $request): string
    {
        $this->authorize('reports.view');
        $data = Database::select(
            'SELECT gs.grade, COUNT(cr.id) AS count
               FROM course_results cr
               JOIN grade_scales gs ON cr.total_score BETWEEN gs.min_score AND gs.max_score
              WHERE cr.is_published=1
              GROUP BY gs.grade ORDER BY gs.min_score DESC'
        );
        return $this->view('reports.academic', ['pageTitle' => 'Academic Report', 'gradeData' => $data]);
    }

    public function attendance(Request $request): string
    {
        $this->authorize('reports.view');
        $data = Database::select(
            'SELECT c.code, c.title,
                    COUNT(ar.id) AS total_records,
                    SUM(CASE WHEN ar.status IN ("present","late") THEN 1 ELSE 0 END) AS attended,
                    ROUND(SUM(CASE WHEN ar.status IN ("present","late") THEN 1 ELSE 0 END) / COUNT(ar.id) * 100, 1) AS rate
               FROM attendance_records ar
               JOIN attendance_sessions s ON s.id = ar.session_id
               JOIN course_offerings o    ON o.id = s.offering_id
               JOIN courses c             ON c.id = o.course_id
              GROUP BY c.id ORDER BY rate'
        );
        return $this->view('reports.attendance', ['pageTitle' => 'Attendance Report', 'data' => $data]);
    }

    public function export(Request $request): never
    {
        $this->authorize('reports.export');
        // Basic CSV export stub
        $type = $request->query('type', 'enrolment');
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report-' . $type . '-' . date('Y-m-d') . '.csv"');
        echo "Report export not yet implemented for: {$type}\n";
        exit;
    }
}
