<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\ResourceController;
use App\Models\Scholarship;

final class ScholarshipController extends ResourceController
{
    protected string $title = 'Scholarships';
    protected string $entity = 'Scholarship';
    protected string $routeBase = '/finance/scholarships';
    protected string $permission = 'finance';
    protected string $icon = 'money';
    protected string $lede = 'Bursaries and scholarships that discount student fees.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Scholarship();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Scholarship', 'type' => 'strong'],
            ['key' => 'sponsor', 'label' => 'Sponsor'],
            ['key' => 'award_type', 'label' => 'Award', 'type' => 'humanize'],
            ['key' => 'percentage', 'label' => 'Percent', 'type' => 'number', 'align' => 'center'],
            ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Scholarship name', 'width' => 6, 'required' => true],
            ['name' => 'sponsor', 'label' => 'Sponsor', 'width' => 6],
            ['name' => 'award_type', 'label' => 'Award type', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['full', 'partial', 'fixed_amount']],
            ['name' => 'percentage', 'label' => 'Percentage of tuition', 'type' => 'number', 'width' => 4],
            ['name' => 'amount', 'label' => 'Fixed amount', 'type' => 'number', 'width' => 4],
            ['name' => 'slots', 'label' => 'Available slots', 'type' => 'number', 'width' => 4],
            ['name' => 'criteria', 'label' => 'Award criteria', 'type' => 'textarea', 'width' => 12],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['active', 'inactive']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'name' => 'required|max:150',
            'sponsor' => 'nullable|max:150',
            'award_type' => 'required',
            'percentage' => 'nullable|numeric|between:0,100',
            'amount' => 'nullable|numeric|min:0',
            'slots' => 'nullable|integer|min:0',
            'criteria' => 'nullable|max:2000',
            'status' => 'required|in:active,inactive',
        ];
    }
}
