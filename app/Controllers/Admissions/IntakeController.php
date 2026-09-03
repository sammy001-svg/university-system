<?php
declare(strict_types=1);

namespace App\Controllers\Admissions;

use App\Core\ResourceController;
use App\Models\AcademicYear;
use App\Models\Intake;

final class IntakeController extends ResourceController
{
    protected string $title = 'Intakes';
    protected string $entity = 'Intake';
    protected string $routeBase = '/admissions/intakes';
    protected string $permission = 'admissions';
    protected string $icon = 'calendar';
    protected string $lede = 'Admission intakes and their application windows.';
    protected string $defaultSort = 'start_date';
    protected string $defaultDir = 'desc';

    public function __construct()
    {
        $this->model = new Intake();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Intake', 'type' => 'strong'],
            ['key' => 'start_date', 'label' => 'Starts', 'type' => 'date'],
            ['key' => 'application_close', 'label' => 'Applications close', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Intake name', 'width' => 6, 'required' => true],
            ['name' => 'code', 'label' => 'Intake code', 'width' => 3, 'required' => true],
            ['name' => 'academic_year_id', 'label' => 'Academic year', 'type' => 'select', 'width' => 3, 'required' => true, 'options' => (new AcademicYear())->options()],
            ['name' => 'start_date', 'label' => 'Reporting date', 'type' => 'date', 'width' => 4],
            ['name' => 'application_open', 'label' => 'Applications open', 'type' => 'date', 'width' => 4],
            ['name' => 'application_close', 'label' => 'Applications close', 'type' => 'date', 'width' => 4],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['planned', 'open', 'closed']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'name' => 'required|max:80',
            'code' => 'required|max:30|unique:intakes,code,' . ($id ?? ''),
            'academic_year_id' => 'required|integer|exists:academic_years,id',
            'start_date' => 'nullable|date',
            'application_open' => 'nullable|date',
            'application_close' => 'nullable|date',
            'status' => 'required|in:planned,open,closed',
        ];
    }
}
