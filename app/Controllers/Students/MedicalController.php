<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\ResourceController;
use App\Models\MedicalRecord;
use App\Models\Student;

final class MedicalController extends ResourceController
{
    protected string $title = 'Health Records';
    protected string $entity = 'Health Record';
    protected string $routeBase = '/services/medical';
    protected string $permission = 'medical';
    protected string $icon = 'hospital';
    protected string $lede = 'Confidential clinic visits recorded by the university health unit.';
    protected string $defaultSort = 'visit_date';
    protected string $defaultDir = 'desc';

    public function __construct()
    {
        $this->model = new MedicalRecord();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'visit_date', 'label' => 'Visit', 'type' => 'datetime'],
            ['key' => 'complaint', 'label' => 'Presenting complaint', 'type' => 'strong'],
            ['key' => 'diagnosis', 'label' => 'Diagnosis'],
            ['key' => 'attended_by', 'label' => 'Attended by'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'student_id', 'label' => 'Student', 'type' => 'select', 'width' => 6, 'required' => true, 'options' => (new Student())->options()],
            ['name' => 'visit_date', 'label' => 'Date and time of visit', 'type' => 'datetime-local', 'width' => 6, 'required' => true],
            ['name' => 'complaint', 'label' => 'Presenting complaint', 'width' => 12, 'required' => true],
            ['name' => 'diagnosis', 'label' => 'Diagnosis', 'width' => 12],
            ['name' => 'treatment', 'label' => 'Treatment and prescription', 'type' => 'textarea', 'width' => 12],
            ['name' => 'attended_by', 'label' => 'Attended by', 'width' => 6],
            ['name' => 'is_confidential', 'label' => 'Confidential record', 'type' => 'checkbox', 'width' => 6],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'student_id' => 'required|integer|exists:students,id',
            'visit_date' => 'required|date',
            'complaint' => 'required|max:255',
            'diagnosis' => 'nullable|max:255',
            'treatment' => 'nullable|max:3000',
            'attended_by' => 'nullable|max:150',
            'is_confidential' => 'nullable|integer',
        ];
    }
}
