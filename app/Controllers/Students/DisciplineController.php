<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\ResourceController;
use App\Models\DisciplinaryCase;
use App\Models\Student;

final class DisciplineController extends ResourceController
{
    protected string $title = 'Disciplinary Cases';
    protected string $entity = 'Case';
    protected string $routeBase = '/services/discipline';
    protected string $permission = 'discipline';
    protected string $icon = 'shield';
    protected string $lede = 'Student discipline register with hearings and penalties.';
    protected string $defaultSort = 'incident_date';
    protected string $defaultDir = 'desc';

    public function __construct()
    {
        $this->model = new DisciplinaryCase();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'case_number', 'label' => 'Case', 'type' => 'code'],
            ['key' => 'offence', 'label' => 'Offence', 'type' => 'strong'],
            ['key' => 'incident_date', 'label' => 'Incident', 'type' => 'date'],
            ['key' => 'penalty', 'label' => 'Penalty', 'type' => 'humanize'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'case_number', 'label' => 'Case number', 'width' => 4, 'required' => true],
            ['name' => 'student_id', 'label' => 'Student', 'type' => 'select', 'width' => 8, 'required' => true, 'options' => (new Student())->options()],
            ['name' => 'offence', 'label' => 'Offence', 'width' => 12, 'required' => true],
            ['name' => 'description', 'label' => 'Details of the incident', 'type' => 'textarea', 'width' => 12],
            ['name' => 'incident_date', 'label' => 'Incident date', 'type' => 'date', 'width' => 4],
            ['name' => 'hearing_date', 'label' => 'Hearing date', 'type' => 'date', 'width' => 4],
            ['name' => 'penalty', 'label' => 'Penalty', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['none', 'warning', 'fine', 'suspension', 'expulsion', 'community_service', 'probation']],
            ['name' => 'fine_amount', 'label' => 'Fine amount', 'type' => 'number', 'width' => 4],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['reported', 'under_investigation', 'hearing', 'closed', 'appealed']],
            ['name' => 'verdict', 'label' => 'Verdict and sanctions', 'type' => 'textarea', 'width' => 12],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'case_number' => 'required|max:30|unique:disciplinary_cases,case_number,' . ($id ?? ''),
            'student_id' => 'required|integer|exists:students,id',
            'offence' => 'required|max:200',
            'description' => 'nullable|max:3000',
            'incident_date' => 'nullable|date',
            'hearing_date' => 'nullable|date',
            'penalty' => 'required',
            'fine_amount' => 'nullable|numeric|min:0',
            'status' => 'required',
            'verdict' => 'nullable|max:3000',
        ];
    }
}
