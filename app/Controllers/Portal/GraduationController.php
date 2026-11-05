<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Services\Academic\DegreeAudit;
use App\Services\Academic\GraduationService;

/** The student's graduation application, and afterwards their alumni profile. */
final class GraduationController extends Controller
{
    use CurrentStudent;

    public function show(Request $request): string
    {
        $student = $this->student();
        $audit   = DegreeAudit::for((int) $student['id']);
        return $this->view('portal.graduation', [
            'pageTitle'  => 'Graduation',
            'student'    => $student,
            'audit'      => $audit,
            'record'     => Database::selectOne('SELECT * FROM graduations WHERE student_id = ? ORDER BY id DESC LIMIT 1', [$student['id']]),
            'blocked'    => GraduationService::canApply($student, $audit),
            'profile'    => Database::selectOne('SELECT * FROM alumni_profiles WHERE student_id = ?', [$student['id']]),
        ]);
    }

    public function apply(Request $request): never
    {
        $this->verifyCsrf($request);
        $student = $this->student();
        $reason  = GraduationService::canApply($student, DegreeAudit::for((int) $student['id']));
        if ($reason !== null) {
            $this->error($reason, '/portal/graduation');
        }
        GraduationService::apply((int) $student['id'], (string) $request->input('name_on_certificate', ''), (int) Auth::id());
        $this->success('Your application to graduate has been received. The Office of Admission and Records will review it.', '/portal/graduation');
    }

    public function profile(Request $request): never
    {
        $this->verifyCsrf($request);
        $student = $this->student();
        if (!in_array($student['status'], ['graduated', 'alumni'], true)) {
            $this->error('The alumni profile opens once you have graduated.', '/portal/graduation');
        }
        $f = static fn (string $k, int $max) => mb_substr(trim((string) $request->input($k, '')), 0, $max) ?: null;
        $url = $f('linkedin_url', 255);
        Database::statement(
            'INSERT INTO alumni_profiles (student_id, employer, job_title, industry, city, country, email, phone, linkedin_url, further_study, in_directory, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE employer = VALUES(employer), job_title = VALUES(job_title), industry = VALUES(industry), city = VALUES(city),
                 country = VALUES(country), email = VALUES(email), phone = VALUES(phone), linkedin_url = VALUES(linkedin_url),
                 further_study = VALUES(further_study), in_directory = VALUES(in_directory), updated_at = VALUES(updated_at)',
            [$student['id'], $f('employer', 150), $f('job_title', 150), $f('industry', 100), $f('city', 100), $f('country', 100),
             filter_var($f('email', 150), FILTER_VALIDATE_EMAIL) ?: null, $f('phone', 30),
             $url !== null && preg_match('#^https?://#i', $url) ? $url : null, $f('further_study', 200),
             $request->input('in_directory') === '1' ? 1 : 0, date('Y-m-d H:i:s')]
        );
        $this->success('Alumni profile saved. Thank you for staying in touch!', '/portal/graduation');
    }
}
