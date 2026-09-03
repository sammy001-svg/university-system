<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\Campus;
use App\Models\Faculty;
use App\Models\User;

final class FacultyController extends ResourceController
{
    protected string $title = 'Faculties';
    protected string $entity = 'Faculty';
    protected string $routeBase = '/academics/faculties';
    protected string $permission = 'academics';
    protected string $icon = 'building';
    protected string $lede = 'Schools and faculties that group the academic departments.';
    protected string $defaultSort = 'code';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Faculty();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $query = QueryBuilder::table('faculties', 'f')
            ->select(
                'f.*', 'c.name AS campus_name',
                'CONCAT(u.first_name, " ", u.last_name) AS dean_name',
                '(SELECT COUNT(*) FROM departments d WHERE d.faculty_id = f.id) AS departments_count'
            )
            ->leftJoin('campuses c', 'c.id = f.campus_id')
            ->leftJoin('users u', 'u.id = f.dean_id');

        if ($request->filled('status')) {
            $query->where('f.status', $request->query('status'));
        }
        if ($request->filled('campus_id')) {
            $query->where('f.campus_id', $request->query('campus_id'));
        }
        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Faculty', 'type' => 'strong'],
            ['key' => 'campus_name', 'label' => 'Campus'],
            ['key' => 'dean_name', 'label' => 'Dean'],
            ['key' => 'departments_count', 'label' => 'Departments', 'type' => 'number', 'align' => 'center', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        return [
            ['name' => 'campus_id', 'label' => 'Campus', 'options' => (new Campus())->options('name'), 'width' => 3],
            ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'width' => 3],
        ];
    }

    protected function formData(): array
    {
        return [];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Faculty code', 'width' => 4, 'required' => true, 'placeholder' => 'e.g. FST'],
            ['name' => 'name', 'label' => 'Faculty name', 'width' => 8, 'required' => true],
            ['name' => 'campus_id', 'label' => 'Campus', 'type' => 'select', 'width' => 6,
             'options' => (new Campus())->options('name')],
            ['name' => 'dean_id', 'label' => 'Dean', 'type' => 'select', 'width' => 6,
             'options' => $this->deanOptions()],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'width' => 4],
            ['name' => 'phone', 'label' => 'Phone', 'width' => 4],
            ['name' => 'established_year', 'label' => 'Established', 'type' => 'number', 'width' => 4,
             'min' => 1800, 'max' => (int) date('Y')],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'width' => 12, 'rows' => 3],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true,
             'values' => ['active', 'inactive'], 'default' => 'active'],
        ];
    }

    private function deanOptions(): array
    {
        $rows = Database::select(
            "SELECT u.id, CONCAT(COALESCE(u.title,''), ' ', u.first_name, ' ', u.last_name) AS label
               FROM users u JOIN staff s ON s.user_id = u.id
              WHERE u.status = 'active' ORDER BY u.last_name"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['id']] = trim((string) preg_replace('/\s+/', ' ', $row['label']));
        }
        return $out;
    }

    protected function rules(?int $id): array
    {
        return [
            'code'             => 'required|max:20|unique:faculties,code,' . ($id ?? ''),
            'name'             => 'required|max:150',
            'campus_id'        => 'nullable|integer',
            'dean_id'          => 'nullable|integer',
            'email'            => 'nullable|email|max:120',
            'phone'            => 'nullable|phone',
            'established_year' => 'nullable|integer|between:1800,' . date('Y'),
            'description'      => 'nullable|max:2000',
            'status'           => 'required|in:active,inactive',
        ];
    }

    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        $data['code']      = strtoupper((string) $data['code']);
        $data['campus_id'] = $data['campus_id'] ?: null;
        $data['dean_id']   = $data['dean_id'] ?: null;
        return $data;
    }

    protected function guardDelete(array $record): ?string
    {
        $count = (int) Database::scalar('SELECT COUNT(*) FROM departments WHERE faculty_id = ?', [$record['id']]);
        return $count > 0
            ? "This faculty still has {$count} department(s). Move or remove them first."
            : null;
    }
}
