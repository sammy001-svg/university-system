<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\Course;
use App\Models\Department;

final class CourseController extends ResourceController
{
    protected string $title = 'Courses';
    protected string $entity = 'Course';
    protected string $routeBase = '/academics/courses';
    protected string $permission = 'academics';
    protected string $icon = 'book';
    protected string $lede = 'Course catalogue with credit hours and contact-hour breakdown.';
    protected string $defaultSort = 'code';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Course();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $query = (new Course())->listing();
        if ($request->filled('department_id')) {
            $query->where('c.department_id', $request->query('department_id'));
        }
        if ($request->filled('level')) {
            $query->where('c.level', $request->query('level'));
        }
        if ($request->filled('status')) {
            $query->where('c.status', $request->query('status'));
        }
        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'title', 'label' => 'Course title', 'type' => 'strong', 'limit' => 60],
            ['key' => 'department_name', 'label' => 'Department', 'sortable' => false],
            ['key' => 'credit_hours', 'label' => 'Credits', 'type' => 'number', 'align' => 'center'],
            ['key' => 'level', 'label' => 'Year', 'type' => 'number', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        return [
            ['name' => 'department_id', 'label' => 'Department', 'options' => (new Department())->options('name'), 'width' => 3],
            ['name' => 'level', 'label' => 'Year of study', 'width' => 2,
             'options' => [1 => 'Year 1', 2 => 'Year 2', 3 => 'Year 3', 4 => 'Year 4', 5 => 'Year 5', 6 => 'Year 6']],
            ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive'], 'width' => 2],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Course code', 'width' => 4, 'required' => true, 'placeholder' => 'e.g. CS 101'],
            ['name' => 'title', 'label' => 'Course title', 'width' => 8, 'required' => true],
            ['name' => 'department_id', 'label' => 'Owning department', 'type' => 'select', 'width' => 6, 'required' => true,
             'options' => (new Department())->options('name')],
            ['name' => 'credit_hours', 'label' => 'Credit hours', 'type' => 'number', 'width' => 3,
             'required' => true, 'min' => 1, 'max' => 12, 'default' => 3],
            ['name' => 'level', 'label' => 'Year of study', 'type' => 'number', 'width' => 3,
             'required' => true, 'min' => 1, 'max' => 6, 'default' => 1],
            ['name' => 'lecture_hours', 'label' => 'Lecture hours', 'type' => 'number', 'width' => 4, 'min' => 0, 'default' => 30,
             'group' => 'Contact hours per semester'],
            ['name' => 'tutorial_hours', 'label' => 'Tutorial hours', 'type' => 'number', 'width' => 4, 'min' => 0, 'default' => 15],
            ['name' => 'practical_hours', 'label' => 'Practical hours', 'type' => 'number', 'width' => 4, 'min' => 0, 'default' => 0],
            ['name' => 'description', 'label' => 'Course description', 'type' => 'textarea', 'width' => 12, 'rows' => 3,
             'group' => 'Syllabus'],
            ['name' => 'objectives', 'label' => 'Learning outcomes', 'type' => 'textarea', 'width' => 12, 'rows' => 3],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true,
             'values' => ['active', 'inactive'], 'default' => 'active'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code'            => 'required|max:25|unique:courses,code,' . ($id ?? ''),
            'title'           => 'required|max:200',
            'department_id'   => 'required|integer|exists:departments,id',
            'credit_hours'    => 'required|integer|between:1,12',
            'level'           => 'required|integer|between:1,6',
            'lecture_hours'   => 'nullable|integer|between:0,255',
            'tutorial_hours'  => 'nullable|integer|between:0,255',
            'practical_hours' => 'nullable|integer|between:0,255',
            'description'     => 'nullable|max:3000',
            'objectives'      => 'nullable|max:3000',
            'status'          => 'required|in:active,inactive',
        ];
    }

    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        $data['code'] = strtoupper((string) $data['code']);
        return $data;
    }

    protected function guardDelete(array $record): ?string
    {
        $offerings = (int) Database::scalar('SELECT COUNT(*) FROM course_offerings WHERE course_id = ?', [$record['id']]);
        return $offerings > 0
            ? "This course has {$offerings} class offering(s) and cannot be deleted."
            : null;
    }
}
