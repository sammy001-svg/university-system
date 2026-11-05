<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Academic\DegreeAudit;
use App\Services\Academic\GraduationService;
use App\Support\Lookup;
use RuntimeException;

/** The registrar's graduation list and the alumni directory. */
final class GraduationController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('graduation.view');
        $yearId = (int) $request->query('year', GraduationService::currentYearId());
        $status = (string) $request->query('status', '');
        $sql = 'SELECT g.*, s.admission_number, s.year_of_study, u.first_name, u.last_name, p.name AS program_name, p.code AS program_code,
                       (SELECT COUNT(*) FROM clearances c WHERE c.student_id = s.id AND c.status <> "cleared") AS open_clearances,
                       (SELECT SUM(quality_points) / NULLIF(SUM(credit_hours), 0) FROM course_results r WHERE r.student_id = s.id AND r.is_published = 1) AS cgpa
                  FROM graduations g JOIN students s ON s.id = g.student_id JOIN users u ON u.id = s.user_id
                  JOIN programs p ON p.id = COALESCE(g.program_id, s.program_id)
                 WHERE g.academic_year_id = ?' . ($status !== '' ? ' AND g.status = ?' : '') . '
                 ORDER BY p.name, u.last_name, u.first_name';
        $rows = Database::select($sql, $status !== '' ? [$yearId, $status] : [$yearId]);

        if ($request->query('export') === 'csv') {
            Response::csv(array_map(static fn (array $r): array => [$r['admission_number'], $r['name_on_certificate'] ?: $r['first_name'] . ' ' . $r['last_name'],
                $r['program_name'], GraduationService::STATUSES[$r['status']], $r['final_cgpa'] > 0 ? $r['final_cgpa'] : round((float) $r['cgpa'], 2),
                $r['classification'] ?? '', $r['certificate_no'] ?? '', $r['graduation_date'] ?? ''], $rows),
                'graduation-list.csv', ['Student ID', 'Name on certificate', 'Programme', 'Status', 'CGPA', 'Classification', 'Certificate no.', 'Graduated']);
        }

        // Final-year students who have not applied: the registrar can add them.
        $candidates = Database::select(
            'SELECT s.id, s.admission_number, u.first_name, u.last_name, p.name AS program_name, s.year_of_study
               FROM students s JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
              WHERE s.status = "active" AND s.year_of_study >= FLOOR(p.duration_years)
                AND NOT EXISTS (SELECT 1 FROM graduations g WHERE g.student_id = s.id AND g.status <> "deferred")
              ORDER BY p.name, u.last_name LIMIT 300'
        );
        return $this->view('students.graduation', [
            'pageTitle'  => 'Graduation',
            'rows'       => $rows,
            'years'      => Lookup::academicYears(),
            'yearId'     => $yearId,
            'status'     => $status,
            'candidates' => $candidates,
            'counts'     => array_column(Database::select('SELECT status, COUNT(*) AS n FROM graduations WHERE academic_year_id = ? GROUP BY status', [$yearId]), 'n', 'status'),
            'canManage'  => Auth::can('graduation.manage'),
        ]);
    }

    public function add(Request $request): never
    {
        $this->authorize('graduation.manage');
        $this->verifyCsrf($request);
        $ids = array_map('intval', (array) $request->input('student_ids', []));
        foreach ($ids as $id) {
            GraduationService::apply($id, null, (int) Auth::id());
        }
        $this->success(count($ids) . ' student(s) added to the graduation list.', '/graduation');
    }

    public function advance(Request $request, string $id): never
    {
        $this->authorize('graduation.manage');
        $this->verifyCsrf($request);
        $ids = $id === 'bulk' ? array_map('intval', (array) $request->input('ids', [])) : [(int) $id];
        $done = 0;
        $errors = [];
        foreach ($ids as $gid) {
            try {
                $message = GraduationService::advance($gid, (string) $request->input('action'), trim((string) $request->input('remarks', '')) ?: null,
                    $request->input('graduation_date') ?: null, (int) Auth::id());
                $done++;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($errors !== []) {
            $this->warning(($done ? $done . ' updated. ' : '') . implode(' ', array_slice(array_unique($errors), 0, 3)), '/graduation');
        }
        $this->success($done === 1 && isset($message) ? $message : $done . ' graduand(s) updated.', '/graduation');
    }

    /** The degree audit next to the graduation record. */
    public function audit(Request $request, string $studentId): never
    {
        $this->authorize('graduation.view');
        $this->redirect('/students/' . (int) $studentId . '/degree-audit');
    }

    public function alumni(Request $request): string
    {
        $this->authorize('graduation.view');
        $params = [];
        $sql = 'SELECT s.id, s.admission_number, u.first_name, u.last_name, u.email AS account_email, p.name AS program_name,
                       g.graduation_date, g.classification, g.certificate_no, a.employer, a.job_title, a.industry, a.city, a.country,
                       COALESCE(a.email, u.email) AS email, COALESCE(a.phone, u.phone) AS phone, a.further_study, a.updated_at AS profile_updated
                  FROM graduations g JOIN students s ON s.id = g.student_id JOIN users u ON u.id = s.user_id
                  JOIN programs p ON p.id = COALESCE(g.program_id, s.program_id)
             LEFT JOIN alumni_profiles a ON a.student_id = s.id
                 WHERE g.status = "graduated"';
        if ($request->filled('year')) {
            $sql .= ' AND YEAR(g.graduation_date) = ?';
            $params[] = (int) $request->query('year');
        }
        if ($request->filled('program_id')) {
            $sql .= ' AND p.id = ?';
            $params[] = (int) $request->query('program_id');
        }
        if ($request->filled('q')) {
            $sql .= ' AND (u.first_name LIKE ? OR u.last_name LIKE ? OR a.employer LIKE ? OR a.job_title LIKE ? OR s.admission_number LIKE ?)';
            $params = array_merge($params, array_fill(0, 5, '%' . $request->query('q') . '%'));
        }
        $rows = Database::select($sql . ' ORDER BY g.graduation_date DESC, u.last_name', $params);
        if ($request->query('export') === 'csv') {
            Response::csv(array_map(static fn (array $r): array => [$r['admission_number'], $r['first_name'] . ' ' . $r['last_name'], $r['program_name'],
                $r['graduation_date'], $r['classification'], $r['employer'], $r['job_title'], $r['industry'], $r['city'], $r['country'], $r['email'], $r['phone']], $rows),
                'alumni.csv', ['Student ID', 'Name', 'Programme', 'Graduated', 'Classification', 'Employer', 'Job title', 'Industry', 'City', 'Country', 'Email', 'Phone']);
        }
        $employed = count(array_filter($rows, static fn (array $r): bool => trim((string) $r['employer']) !== ''));
        return $this->view('students.alumni', [
            'pageTitle' => 'Alumni',
            'rows'      => $rows,
            'programs'  => Lookup::programs(),
            'years'     => array_column(Database::select('SELECT DISTINCT YEAR(graduation_date) AS y FROM graduations WHERE status = "graduated" ORDER BY y DESC'), 'y'),
            'employed'  => $employed,
            'audits'    => null,
        ]);
    }
}
