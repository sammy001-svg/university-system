<?php
declare(strict_types=1);

namespace App\Controllers\HR;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Notify;
use App\Core\Request;
use App\Core\Session;
use App\Core\Upload;
use RuntimeException;

/** The public Careers page: open vacancies and online applications (no sign-in needed). */
final class CareersController extends Controller
{
    public function index(Request $request): string
    {
        return $this->view('careers.index', [
            'pageTitle' => 'Careers',
            'vacancies' => Database::select(
                'SELECT v.*, d.name AS department_name FROM job_vacancies v LEFT JOIN departments d ON d.id = v.department_id
                  WHERE v.status = "open" AND v.closing_date >= ? ORDER BY v.closing_date',
                [date('Y-m-d')]
            ),
        ]);
    }

    public function show(Request $request, string $id): string
    {
        return $this->view('careers.show', ['pageTitle' => 'Careers', 'vacancy' => $this->vacancy((int) $id), 'done' => Session::getFlash('applied')]);
    }

    public function apply(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        $vacancy = $this->vacancy((int) $id);
        $back    = '/careers/' . $id;
        $data = $this->validate($request, [
            'full_name' => 'required|max:150', 'email' => 'required|email|max:150', 'phone' => 'nullable|max:30',
            'qualification' => 'nullable|max:150', 'cover_letter' => 'nullable',
        ], ['full_name' => 'Full name']);
        if (Database::scalar('SELECT 1 FROM job_applications WHERE vacancy_id = ? AND email = ?', [$vacancy['id'], $data['email']])) {
            $this->error('An application with this email has already been received for this position.', $back);
        }
        $file = $request->file('cv');
        if ($file === null) {
            Session::keepOldInput($request->all());
            $this->error('Attach your CV (PDF or Word).', $back);
        }
        try {
            $cv = Upload::storePrivate($file, 'cvs/' . $vacancy['id'], ['pdf', 'doc', 'docx'], 10);
        } catch (RuntimeException $e) {
            Session::keepOldInput($request->all());
            $this->error($e->getMessage(), $back);
        }
        Database::statement(
            'INSERT INTO job_applications (vacancy_id, full_name, email, phone, qualification, cover_letter, cv_path, cv_name, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$vacancy['id'], $data['full_name'], $data['email'], ($data['phone'] ?? '') ?: null, ($data['qualification'] ?? '') ?: null,
             trim((string) ($data['cover_letter'] ?? '')) ?: null, $cv['path'], $cv['name'], date('Y-m-d H:i:s')]
        );
        Notify::toRole('hr-officer', 'New job application', $data['full_name'] . ' applied for ' . $vacancy['title'] . '.', '/hr/vacancies/' . $vacancy['id'] . '/applicants');
        Session::clearOldInput();
        Session::flash('applied', true);
        $this->redirect($back);
    }

    private function vacancy(int $id): array
    {
        $v = Database::selectOne(
            'SELECT v.*, d.name AS department_name FROM job_vacancies v LEFT JOIN departments d ON d.id = v.department_id WHERE v.id = ? AND v.status = "open"',
            [$id]
        );
        if ($v === null || $v['closing_date'] < date('Y-m-d')) {
            throw new HttpException(404, 'This vacancy is closed or no longer advertised.');
        }
        return $v;
    }
}
