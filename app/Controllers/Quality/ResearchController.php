<?php
declare(strict_types=1);

namespace App\Controllers\Quality;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\ResearchOutput;
use App\Support\Lookup;

/**
 * Research output: publications, grants, projects and supervision. Every staff member
 * records their own; research.view sees the whole institution and research.manage may
 * record for anyone.
 */
final class ResearchController extends ResourceController
{
    protected string $title = 'Research & Publications';
    protected string $entity = 'Research output';
    protected string $routeBase = '/research';
    protected string $permission = 'research';
    protected string $icon = 'book';
    protected string $lede = 'Publications, conference papers, books, grants and research projects by university staff.';
    protected string $defaultSort = 'year';
    protected string $defaultDir = 'desc';

    private const TYPES    = ['journal_article', 'conference_paper', 'book', 'book_chapter', 'report', 'thesis_supervision', 'patent', 'grant', 'project', 'other'];
    private const STATUSES = ['published', 'in_press', 'submitted', 'ongoing', 'completed', 'awarded'];

    public function __construct()
    {
        $this->model = new ResearchOutput();
    }

    private function seesAll(): bool
    {
        return Auth::can('research.view') || Auth::can('research.manage');
    }

    protected function allowed(string $action): bool
    {
        if ($action === 'view') {
            return $this->seesAll() || Lookup::myStaffId() !== null;
        }
        return Auth::can('research.manage') || Lookup::myStaffId() !== null;
    }

    /** Staff may change only their own records unless they manage research. */
    private function ownOrManage(int $id): void
    {
        $row = $this->model->find($id) ?? throw new HttpException(404);
        if (!Auth::can('research.manage') && (int) $row['staff_id'] !== Lookup::myStaffId()) {
            throw new HttpException(403, 'You can change only your own research records.');
        }
    }

    public function edit(Request $request, string $id): string
    {
        $this->ownOrManage((int) $id);
        return parent::edit($request, $id);
    }

    public function update(Request $request, string $id): never
    {
        $this->ownOrManage((int) $id);
        parent::update($request, $id);
    }

    public function destroy(Request $request, string $id): never
    {
        $this->ownOrManage((int) $id);
        parent::destroy($request, $id);
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $q = $this->model->query('r')
            ->select('r.*', 'CONCAT(u.first_name, " ", u.last_name) AS staff_name', 'COALESCE(d.name, f.name) AS unit_name')
            ->join('staff s', 's.id = r.staff_id')
            ->join('users u', 'u.id = s.user_id')
            ->leftJoin('departments d', 'd.id = s.department_id')
            ->leftJoin('faculties f', 'f.id = COALESCE(d.faculty_id, s.faculty_id)');
        if (!$this->seesAll() || $request->query('mine') === '1') {
            $q->where('r.staff_id', Lookup::myStaffId() ?? 0);
        }
        foreach (['output_type', 'year', 'status'] as $f) {
            if ($request->filled($f)) {
                $q->where('r.' . $f, $request->query($f));
            }
        }
        if ($request->filled('faculty_id')) {
            $q->where('f.id', $request->query('faculty_id'));
        }
        return $q;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'year', 'label' => 'Year', 'type' => 'number'],
            ['key' => 'title', 'label' => 'Title', 'type' => 'strong'],
            ['key' => 'output_type', 'label' => 'Type', 'type' => 'humanize'],
            ['key' => 'venue', 'label' => 'Journal / venue / funder'],
            ['key' => 'staff_name', 'label' => 'Staff member'],
            ['key' => 'unit_name', 'label' => 'College / department'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        $years = array_column(Database::select('SELECT DISTINCT year FROM research_outputs ORDER BY year DESC'), 'year');
        $out = [
            ['name' => 'output_type', 'label' => 'Type', 'options' => array_combine(self::TYPES, array_map('humanize', self::TYPES))],
            ['name' => 'year', 'label' => 'Year', 'options' => array_combine($years, $years) ?: [], 'width' => 2],
        ];
        if ($this->seesAll()) {
            $out[] = ['name' => 'faculty_id', 'label' => 'College', 'options' => array_column(Database::select('SELECT id, name FROM faculties ORDER BY name'), 'name', 'id')];
            $out[] = ['name' => 'mine', 'label' => 'Show', 'options' => ['1' => 'Only mine'], 'placeholder' => 'Everyone', 'width' => 2];
        }
        return $out;
    }

    protected function fields(): array
    {
        $fields = [];
        if (Auth::can('research.manage')) {
            $fields[] = ['name' => 'staff_id', 'label' => 'Staff member', 'type' => 'select', 'width' => 12, 'required' => true, 'options' => Lookup::staff(),
                         'default' => Lookup::myStaffId()];
        }
        return array_merge($fields, [
            ['name' => 'output_type', 'label' => 'Type', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => self::TYPES],
            ['name' => 'year', 'label' => 'Year', 'type' => 'number', 'width' => 2, 'required' => true, 'default' => date('Y')],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => self::STATUSES],
            ['name' => 'peer_reviewed', 'label' => 'Peer reviewed', 'type' => 'checkbox', 'width' => 3],
            ['name' => 'title', 'label' => 'Title', 'width' => 12, 'required' => true],
            ['name' => 'authors', 'label' => 'Authors (as cited)', 'width' => 12, 'attrs' => 'placeholder="e.g. Toga, J. P., Kromah, A. D."'],
            ['name' => 'venue', 'label' => 'Journal, conference, publisher or funder', 'width' => 8],
            ['name' => 'amount', 'label' => 'Grant amount', 'type' => 'number', 'width' => 4, 'attrs' => 'step="0.01" min="0"'],
            ['name' => 'url', 'label' => 'DOI or link', 'width' => 12],
            ['name' => 'abstract', 'label' => 'Abstract / summary', 'type' => 'textarea', 'width' => 12, 'rows' => 4],
        ]);
    }

    protected function rules(?int $id): array
    {
        return [
            'staff_id' => 'nullable|integer', 'output_type' => 'required|in:' . implode(',', self::TYPES),
            'year' => 'required|integer|between:1950,2100', 'status' => 'required|in:' . implode(',', self::STATUSES),
            'peer_reviewed' => 'nullable|integer', 'title' => 'required|max:300', 'authors' => 'nullable|max:500',
            'venue' => 'nullable|max:255', 'amount' => 'nullable|numeric', 'url' => 'nullable|max:500', 'abstract' => 'nullable',
        ];
    }

    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        if (!Auth::can('research.manage') || empty($data['staff_id'])) {
            $data['staff_id'] = $id !== null ? (int) $this->model->find($id)['staff_id'] : Lookup::myStaffId();
        }
        $data['peer_reviewed'] = $request->input('peer_reviewed') ? 1 : 0;
        return $data;
    }
}
