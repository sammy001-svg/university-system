<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Staff;

final class DepartmentController extends ResourceController
{
    protected string $title = 'Departments';
    protected string $entity = 'Department';
    protected string $routeBase = '/academics/departments';
    protected string $permission = 'academics';
    protected string $icon = 'building';
    protected string $lede = 'Academic departments that own programmes and courses.';
    protected string $defaultSort = 'code';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Department();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $query = QueryBuilder::table('departments', 'd')
            ->select(
                'd.*', 'f.name AS faculty_name',
                'CONCAT(u.first_name, " ", u.last_name) AS hod_name',
                '(SELECT COUNT(*) FROM programs p WHERE p.department_id = d.id) AS programs_count',
                '(SELECT COUNT(*) FROM courses c WHERE c.department_id = d.id) AS courses_count'
            )
            ->join('faculties f', 'f.id = d.faculty_id')
            ->leftJoin('users u', 'u.id = d.hod_id');

        if ($request->filled('faculty_id')) {
            $query->where('d.faculty_id', $request->query('faculty_id'));
        }
        if ($request->filled('status')) {
            $query->where('d.status', $request->query('status'));
        }
        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Department', 'type' => 'strong'],
            ['key' => 'faculty_name', 'label' => 'Faculty'],
            ['key' => 'hod_name', 'label' => 'Head of department'],
            ['key' => 'programs_count', 'label' => 'Programmes', 'type' => 'number', 'align' => 'center', 'sortable' => false],
            ['key' => 'courses_count', 'label' => 'Courses', 'type' => 'number', 'align' => 'center', 'sortable' => false],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        return [
            ['name' => 'faculty_id', 'label' => 'Faculty', 'options' => (new Faculty())->options('name'), 'width' => 3],
            ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'width' => 3],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Department code', 'width' => 4, 'required' => true, 'placeholder' => 'e.g. CS'],
            ['name' => 'name', 'label' => 'Department name', 'width' => 8, 'required' => true],
            ['name' => 'faculty_id', 'label' => 'Faculty', 'type' => 'select', 'width' => 6, 'required' => true,
             'options' => (new Faculty())->options('name')],
            ['name' => 'hod_id', 'label' => 'Head of department', 'type' => 'select', 'width' => 6,
             'options' => $this->hodOptions()],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'width' => 6],
            ['name' => 'phone', 'label' => 'Phone', 'width' => 6],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'width' => 12, 'rows' => 3],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true,
             'values' => ['active', 'inactive'], 'default' => 'active'],
        ];
    }

    private function hodOptions(): array
    {
        $rows = Database::select(
            "SELECT u.id, CONCAT(COALESCE(u.title,''), ' ', u.first_name, ' ', u.last_name) AS label
               FROM users u JOIN staff s ON s.user_id = u.id
              WHERE u.status = 'active' AND s.staff_category = 'academic'
              ORDER BY u.last_name"
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
            'code'        => 'required|max:20|unique:departments,code,' . ($id ?? ''),
            'name'        => 'required|max:150',
            'faculty_id'  => 'required|integer|exists:faculties,id',
            'hod_id'      => 'nullable|integer',
            'email'       => 'nullable|email|max:120',
            'phone'       => 'nullable|phone',
            'description' => 'nullable|max:2000',
            'status'      => 'required|in:active,inactive',
        ];
    }

    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        $data['code']   = strtoupper((string) $data['code']);
        $data['hod_id'] = $data['hod_id'] ?: null;
        return $data;
    }

    protected function guardDelete(array $record): ?string
    {
        $programs = (int) Database::scalar('SELECT COUNT(*) FROM programs WHERE department_id = ?', [$record['id']]);
        $courses  = (int) Database::scalar('SELECT COUNT(*) FROM courses WHERE department_id = ?', [$record['id']]);
        if ($programs > 0 || $courses > 0) {
            return "This department owns {$programs} programme(s) and {$courses} course(s) and cannot be deleted.";
        }
        return null;
    }
}
