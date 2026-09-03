<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Audit;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\AcademicYear;
use App\Models\Semester;

final class SemesterController extends ResourceController
{
    protected string $title = 'Semesters';
    protected string $entity = 'Semester';
    protected string $routeBase = '/academics/semesters';
    protected string $permission = 'academics';
    protected string $icon = 'calendar';
    protected string $lede = 'Teaching periods, registration windows and examination dates.';
    protected string $defaultSort = 'start_date';

    public function __construct()
    {
        $this->model = new Semester();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $query = QueryBuilder::table('semesters', 's')
            ->select('s.*', 'ay.name AS academic_year')
            ->join('academic_years ay', 'ay.id = s.academic_year_id');

        if ($request->filled('academic_year_id')) {
            $query->where('s.academic_year_id', $request->query('academic_year_id'));
        }
        return $query;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'academic_year', 'label' => 'Academic year', 'sortable' => false],
            ['key' => 'name', 'label' => 'Semester', 'type' => 'strong'],
            ['key' => 'start_date', 'label' => 'Starts', 'type' => 'date'],
            ['key' => 'end_date', 'label' => 'Ends', 'type' => 'date'],
            ['key' => 'registration_end', 'label' => 'Registration closes', 'type' => 'date'],
            ['key' => 'is_current', 'label' => 'Current', 'type' => 'bool', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        return [
            ['name' => 'academic_year_id', 'label' => 'Academic year',
             'options' => (new AcademicYear())->options('name'), 'width' => 3],
        ];
    }

    protected function rowActions(): array
    {
        return [
            ['label' => 'Make current', 'icon' => 'check', 'url' => '/academics/semesters/{id}/activate'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'academic_year_id', 'label' => 'Academic year', 'type' => 'select', 'width' => 6, 'required' => true,
             'options' => (new AcademicYear())->options('name')],
            ['name' => 'name', 'label' => 'Semester name', 'width' => 6, 'required' => true, 'placeholder' => 'e.g. Semester One'],
            ['name' => 'semester_number', 'label' => 'Semester number', 'type' => 'number', 'width' => 3,
             'required' => true, 'min' => 1, 'max' => 4, 'default' => 1],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 3, 'required' => true,
             'values' => ['planned', 'active', 'closed'], 'default' => 'planned'],
            ['name' => 'start_date', 'label' => 'Teaching starts', 'type' => 'date', 'width' => 3, 'required' => true],
            ['name' => 'end_date', 'label' => 'Teaching ends', 'type' => 'date', 'width' => 3, 'required' => true],
            ['name' => 'registration_start', 'label' => 'Registration opens', 'type' => 'date', 'width' => 3,
             'group' => 'Key dates'],
            ['name' => 'registration_end', 'label' => 'Registration closes', 'type' => 'date', 'width' => 3],
            ['name' => 'exam_start', 'label' => 'Exams start', 'type' => 'date', 'width' => 3],
            ['name' => 'exam_end', 'label' => 'Exams end', 'type' => 'date', 'width' => 3],
            ['name' => 'results_published', 'label' => 'Results published', 'type' => 'checkbox', 'width' => 6,
             'checkboxLabel' => 'Students may view their results for this semester'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'academic_year_id'   => 'required|integer|exists:academic_years,id',
            'name'               => 'required|max:60',
            'semester_number'    => 'required|integer|between:1,4',
            'start_date'         => 'required|date',
            'end_date'           => 'required|date|after:start_date',
            'registration_start' => 'nullable|date',
            'registration_end'   => 'nullable|date',
            'exam_start'         => 'nullable|date',
            'exam_end'           => 'nullable|date',
            'results_published'  => 'nullable|integer',
            'status'             => 'required|in:planned,active,closed',
        ];
    }

    /** Flag a semester as the running one. */
    public function activate(Request $request, string $id): never
    {
        $this->authorize('academics.manage');
        $semester = $this->findOrFail($this->model, (int) $id, 'Semester');

        (new Semester())->makeCurrent((int) $id);
        Audit::log('activate', 'academics', 'semesters', $id, 'Set as current semester');

        $this->success($semester['name'] . ' is now the current semester.', $this->routeBase);
    }
}
