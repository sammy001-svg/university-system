<?php
declare(strict_types=1);

namespace App\Controllers\Academics;

use App\Core\Database;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\AcademicYear;

final class AcademicYearController extends ResourceController
{
    protected string $title = 'Academic Years';
    protected string $entity = 'Academic Year';
    protected string $routeBase = '/academics/years';
    protected string $permission = 'academics';
    protected string $icon = 'calendar';
    protected string $lede = 'Academic calendar years and the semesters they contain.';
    protected string $defaultSort = 'start_date';

    public function __construct()
    {
        $this->model = new AcademicYear();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Academic year', 'type' => 'strong'],
            ['key' => 'start_date', 'label' => 'Starts', 'type' => 'date'],
            ['key' => 'end_date', 'label' => 'Ends', 'type' => 'date'],
            ['key' => 'is_current', 'label' => 'Current', 'type' => 'bool', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Academic year', 'width' => 6, 'required' => true, 'placeholder' => 'e.g. 2025/2026'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 6, 'required' => true,
             'values' => ['planned', 'active', 'closed'], 'default' => 'planned'],
            ['name' => 'start_date', 'label' => 'Start date', 'type' => 'date', 'width' => 6, 'required' => true],
            ['name' => 'end_date', 'label' => 'End date', 'type' => 'date', 'width' => 6, 'required' => true],
            ['name' => 'is_current', 'label' => 'Current year', 'type' => 'checkbox', 'width' => 6,
             'checkboxLabel' => 'This is the running academic year'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'name'       => 'required|max:40|unique:academic_years,name,' . ($id ?? ''),
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_current' => 'nullable|integer',
            'status'     => 'required|in:planned,active,closed',
        ];
    }

    protected function afterSave(int $id, array $data, Request $request, bool $isNew): void
    {
        if ((int) ($data['is_current'] ?? 0) === 1) {
            Database::statement('UPDATE academic_years SET is_current = 0 WHERE id != ?', [$id]);
        }
    }

    protected function guardDelete(array $record): ?string
    {
        $semesters = (int) Database::scalar('SELECT COUNT(*) FROM semesters WHERE academic_year_id = ?', [$record['id']]);
        return $semesters > 0
            ? "This year has {$semesters} semester(s) attached and cannot be deleted."
            : null;
    }
}
