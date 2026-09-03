<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\Department;
use App\Models\Program;

final class ProgramController extends ResourceController
{
    protected string $title = 'Programmes';
    protected string $entity = 'Programme';
    protected string $routeBase = '/academics/programs';
    protected string $permission = 'academics';
    protected string $icon = 'book';
    protected string $lede = 'Degrees, diplomas and certificates offered by the institution.';
    protected string $defaultSort = 'code';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Program();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $query = (new Program())->listing();
        if ($request->filled('department_id')) {
            $query->where('p.department_id', $request->query('department_id'));
        }
        if ($request->filled('level')) {
            $query->where('p.level', $request->query('level'));
        }
        if ($request->filled('status')) {
            $query->where('p.status', $request->query('status'));
        }
        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Programme', 'type' => 'link', 'url' => '/academics/programs/{id}/curriculum'],
            ['key' => 'level', 'label' => 'Level', 'type' => 'humanize'],
            ['key' => 'department_name', 'label' => 'Department', 'sortable' => false],
            ['key' => 'duration_years', 'label' => 'Years', 'type' => 'number', 'decimals' => 1, 'align' => 'center'],
            ['key' => 'students_count', 'label' => 'Students', 'type' => 'number', 'align' => 'center', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        $levels = [];
        foreach (Program::LEVELS as $level) {
            $levels[$level] = humanize($level);
        }
        return [
            ['name' => 'department_id', 'label' => 'Department', 'options' => (new Department())->options('name'), 'width' => 3],
            ['name' => 'level', 'label' => 'Level', 'options' => $levels, 'width' => 2],
            ['name' => 'status', 'label' => 'Status', 'width' => 2,
             'options' => ['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived']],
        ];
    }

    protected function rowActions(): array
    {
        return [
            ['label' => 'Curriculum', 'icon' => 'clipboard', 'url' => '/academics/programs/{id}/curriculum'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Programme code', 'width' => 4, 'required' => true, 'placeholder' => 'e.g. BCS'],
            ['name' => 'name', 'label' => 'Programme name', 'width' => 8, 'required' => true,
             'placeholder' => 'e.g. Bachelor of Science in Computer Science'],
            ['name' => 'award', 'label' => 'Award conferred', 'width' => 6, 'placeholder' => 'e.g. BSc (Computer Science)'],
            ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'width' => 6, 'required' => true,
             'options' => (new Department())->options('name')],
            ['name' => 'level', 'label' => 'Study level', 'type' => 'enum', 'width' => 4, 'required' => true,
             'values' => Program::LEVELS, 'default' => 'bachelor'],
            ['name' => 'study_mode', 'label' => 'Study mode', 'type' => 'enum', 'width' => 4, 'required' => true,
             'values' => Program::MODES, 'default' => 'full_time'],
            ['name' => 'duration_years', 'label' => 'Duration (years)', 'type' => 'number', 'width' => 4,
             'required' => true, 'step' => '0.5', 'min' => 0.5, 'max' => 10, 'default' => 4],
            ['name' => 'semesters_per_year', 'label' => 'Semesters / year', 'type' => 'number', 'width' => 4,
             'min' => 1, 'max' => 4, 'default' => 2],
            ['name' => 'total_credit_hours', 'label' => 'Total credit hours', 'type' => 'number', 'width' => 4, 'min' => 0],
            ['name' => 'application_fee', 'label' => 'Application fee', 'type' => 'number', 'width' => 4,
             'step' => '0.01', 'min' => 0, 'default' => 0],
            ['name' => 'min_entry_grade', 'label' => 'Minimum entry grade', 'width' => 4, 'placeholder' => 'e.g. C+'],
            ['name' => 'entry_requirements', 'label' => 'Entry requirements', 'type' => 'textarea', 'width' => 12, 'rows' => 3],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'width' => 12, 'rows' => 3],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true,
             'values' => ['active', 'inactive', 'archived'], 'default' => 'active'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code'               => 'required|max:20|unique:programs,code,' . ($id ?? ''),
            'name'               => 'required|max:180',
            'award'              => 'nullable|max:120',
            'department_id'      => 'required|integer|exists:departments,id',
            'level'              => 'required|in:' . implode(',', Program::LEVELS),
            'study_mode'         => 'required|in:' . implode(',', Program::MODES),
            'duration_years'     => 'required|numeric|between:0.5,10',
            'semesters_per_year' => 'nullable|integer|between:1,4',
            'total_credit_hours' => 'nullable|integer|min:0',
            'application_fee'    => 'nullable|numeric|min:0',
            'min_entry_grade'    => 'nullable|max:20',
            'entry_requirements' => 'nullable|max:3000',
            'description'        => 'nullable|max:3000',
            'status'             => 'required|in:active,inactive,archived',
        ];
    }

    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        $data['code'] = strtoupper((string) $data['code']);
        return $data;
    }

    protected function guardDelete(array $record): ?string
    {
        $students = (int) Database::scalar('SELECT COUNT(*) FROM students WHERE program_id = ?', [$record['id']]);
        return $students > 0
            ? "This programme has {$students} enrolled student(s); archive it instead of deleting."
            : null;
    }
}
