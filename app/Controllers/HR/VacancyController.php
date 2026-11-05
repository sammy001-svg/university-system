<?php
declare(strict_types=1);

namespace App\Controllers\HR;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\JobVacancy;
use App\Support\Lookup;
use App\Support\PrivateFile;

/** Recruitment: vacancies (published on the public Careers page) and their applicants. */
final class VacancyController extends ResourceController
{
    protected string $title = 'Recruitment';
    protected string $entity = 'Vacancy';
    protected string $routeBase = '/hr/vacancies';
    protected string $permission = 'recruitment';
    protected bool $viewManagePermissions = true;
    protected string $icon = 'users';
    protected string $lede = 'Open positions are advertised on the public Careers page, where candidates apply online.';
    protected string $defaultSort = 'closing_date';

    public const STAGES = ['received', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected'];

    public function __construct()
    {
        $this->model = new JobVacancy();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $q = $this->model->query('v')
            ->select('v.*', 'd.name AS department_name',
                '(SELECT COUNT(*) FROM job_applications a WHERE a.vacancy_id = v.id) AS applicants',
                '(SELECT COUNT(*) FROM job_applications a WHERE a.vacancy_id = v.id AND a.status = "received") AS new_applicants')
            ->leftJoin('departments d', 'd.id = v.department_id');
        if ($request->filled('status')) {
            $q->where('v.status', $request->query('status'));
        }
        return $q;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'reference', 'label' => 'Ref', 'type' => 'code'],
            ['key' => 'title', 'label' => 'Position', 'type' => 'strong'],
            ['key' => 'department_name', 'label' => 'Department'],
            ['key' => 'staff_category', 'label' => 'Category', 'type' => 'humanize'],
            ['key' => 'closing_date', 'label' => 'Closes', 'type' => 'date'],
            ['key' => 'applicants', 'label' => 'Applicants', 'type' => 'number', 'align' => 'center'],
            ['key' => 'new_applicants', 'label' => 'New', 'type' => 'number', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        return [['name' => 'status', 'label' => 'Status', 'options' => ['draft' => 'Draft', 'open' => 'Open', 'closed' => 'Closed', 'filled' => 'Filled']]];
    }

    protected function rowActions(): array
    {
        return [['label' => 'Applicants', 'url' => '/hr/vacancies/{id}/applicants', 'icon' => 'users']];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Position title', 'width' => 8, 'required' => true],
            ['name' => 'reference', 'label' => 'Reference', 'width' => 4, 'attrs' => 'placeholder="Created automatically if blank"'],
            ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'width' => 4, 'options' => Lookup::departments()],
            ['name' => 'staff_category', 'label' => 'Category', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => ['academic', 'administrative', 'support', 'technical']],
            ['name' => 'employment_type', 'label' => 'Employment', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => ['permanent', 'contract', 'part_time', 'temporary']],
            ['name' => 'positions', 'label' => 'Positions', 'type' => 'number', 'width' => 2, 'default' => 1],
            ['name' => 'description', 'label' => 'Job description', 'type' => 'textarea', 'width' => 12, 'rows' => 6, 'required' => true],
            ['name' => 'requirements', 'label' => 'Qualifications and requirements', 'type' => 'textarea', 'width' => 12, 'rows' => 5],
            ['name' => 'closing_date', 'label' => 'Closing date', 'type' => 'date', 'width' => 4, 'required' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['draft', 'open', 'closed', 'filled'], 'default' => 'open'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'title' => 'required|max:150', 'reference' => 'nullable|max:30', 'department_id' => 'nullable|integer',
            'staff_category' => 'required|in:academic,administrative,support,technical', 'employment_type' => 'required|in:permanent,contract,part_time,temporary',
            'positions' => 'nullable|integer|between:1,500', 'description' => 'required', 'requirements' => 'nullable',
            'closing_date' => 'required|date', 'status' => 'required|in:draft,open,closed,filled',
        ];
    }

    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        if (trim((string) ($data['reference'] ?? '')) === '') {
            $next = (int) Database::scalar('SELECT COUNT(*) FROM job_vacancies') + 1;
            do {
                $data['reference'] = 'VAC-' . date('Y') . '-' . str_pad((string) $next++, 3, '0', STR_PAD_LEFT);
            } while (Database::scalar('SELECT 1 FROM job_vacancies WHERE reference = ? AND id <> ?', [$data['reference'], $id ?? 0]));
        }
        $data['positions'] = max(1, (int) ($data['positions'] ?? 1));
        return $data;
    }

    protected function guardDelete(array $record): ?string
    {
        return Database::scalar('SELECT 1 FROM job_applications WHERE vacancy_id = ? LIMIT 1', [$record['id']])
            ? 'This vacancy has applicants; close it instead of deleting it.' : null;
    }

    public function applicants(Request $request, string $id): string
    {
        $this->permit('view');
        $vacancy = $this->findOrFail($this->model, (int) $id, 'Vacancy');
        $status  = (string) $request->query('status', '');
        return $this->view('hr.applicants', [
            'pageTitle' => 'Applicants - ' . $vacancy['title'],
            'vacancy'   => $vacancy,
            'rows'      => Database::select('SELECT * FROM job_applications WHERE vacancy_id = ?' . ($status !== '' ? ' AND status = ?' : '') . ' ORDER BY created_at DESC',
                $status !== '' ? [(int) $id, $status] : [(int) $id]),
            'counts'    => array_column(Database::select('SELECT status, COUNT(*) AS n FROM job_applications WHERE vacancy_id = ? GROUP BY status', [(int) $id]), 'n', 'status'),
            'status'    => $status,
            'canManage' => $this->allowed('manage'),
        ]);
    }

    public function moveApplicant(Request $request, string $applicationId): never
    {
        $this->permit('edit');
        $this->verifyCsrf($request);
        $app = Database::selectOne('SELECT * FROM job_applications WHERE id = ?', [(int) $applicationId]) ?? throw new HttpException(404);
        $stage = (string) $request->input('status');
        if (!in_array($stage, self::STAGES, true)) {
            $this->error('Unknown stage.', '/hr/vacancies/' . $app['vacancy_id'] . '/applicants');
        }
        Database::statement('UPDATE job_applications SET status = ?, notes = ? WHERE id = ?',
            [$stage, trim((string) $request->input('notes', $app['notes'] ?? '')) ?: null, $app['id']]);
        Audit::log('recruitment', 'hr', 'job_applications', (string) $app['id'], $app['full_name'] . ' moved to ' . $stage);
        $this->success($app['full_name'] . ': ' . humanize($stage) . '.', '/hr/vacancies/' . $app['vacancy_id'] . '/applicants');
    }

    public function cv(Request $request, string $applicationId): never
    {
        $this->permit('view');
        $app = Database::selectOne('SELECT * FROM job_applications WHERE id = ?', [(int) $applicationId]) ?? throw new HttpException(404);
        PrivateFile::send($app['cv_path'], 'CV - ' . $app['full_name'] . '.' . pathinfo((string) $app['cv_name'], PATHINFO_EXTENSION));
    }
}
