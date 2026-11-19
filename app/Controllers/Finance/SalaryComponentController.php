<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\ResourceController;
use App\Models\SalaryComponent;

/**
 * The allowances the university pays and the deductions it withholds.
 * A component is a template: what each staff member actually gets is set
 * on their salary page.
 */
final class SalaryComponentController extends ResourceController
{
    protected string $title = 'Allowances & Deductions';
    protected string $entity = 'Salary Component';
    protected string $routeBase = '/finance/salary-components';
    protected string $permission = 'payroll';
    protected string $icon = 'money';
    protected string $lede = 'Allowances paid and deductions withheld on a payslip. "Applies to everyone" is for statutory deductions.';
    protected string $defaultSort = 'sort_order';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new SalaryComponent();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Component', 'type' => 'strong'],
            ['key' => 'kind', 'label' => 'Kind', 'type' => 'humanize'],
            ['key' => 'basis', 'label' => 'Worked out as', 'type' => 'humanize'],
            ['key' => 'amount', 'label' => 'Default', 'type' => 'number', 'align' => 'right'],
            ['key' => 'taxable', 'label' => 'Taxable', 'type' => 'bool', 'align' => 'center'],
            ['key' => 'applies_to_all', 'label' => 'Everyone', 'type' => 'bool', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'width' => 3, 'required' => true],
            ['name' => 'name', 'label' => 'Name', 'width' => 9, 'required' => true],
            ['name' => 'kind', 'label' => 'Kind', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['allowance', 'deduction']],
            ['name' => 'basis', 'label' => 'Worked out as', 'type' => 'enum', 'width' => 4, 'required' => true,
             'values' => ['fixed', 'percent_basic', 'percent_gross'], 'hint' => 'A percentage basis reads the amount below as a percentage.'],
            ['name' => 'amount', 'label' => 'Default amount or percentage', 'type' => 'number', 'step' => '0.01', 'width' => 4],
            ['name' => 'taxable', 'label' => 'Counts towards taxable pay', 'type' => 'checkbox', 'width' => 4],
            ['name' => 'applies_to_all', 'label' => 'Applies to every staff member', 'type' => 'checkbox', 'width' => 4],
            ['name' => 'sort_order', 'label' => 'Order on the payslip', 'type' => 'number', 'width' => 4],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'width' => 12],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['active', 'inactive']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code'           => 'required|max:30|unique:salary_components,code,' . ($id ?? ''),
            'name'           => 'required|max:120',
            'kind'           => 'required|in:allowance,deduction',
            'basis'          => 'required|in:fixed,percent_basic,percent_gross',
            'amount'         => 'nullable|numeric|min:0',
            'taxable'        => 'nullable|integer',
            'applies_to_all' => 'nullable|integer',
            'sort_order'     => 'nullable|integer|between:0,999',
            'description'    => 'nullable|max:255',
            'status'         => 'required|in:active,inactive',
        ];
    }
}
