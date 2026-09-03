<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\ResourceController;
use App\Models\FeeType;

final class FeeTypeController extends ResourceController
{
    protected string $title = 'Fee Types';
    protected string $entity = 'Fee Type';
    protected string $routeBase = '/finance/fee-types';
    protected string $permission = 'finance';
    protected string $icon = 'money';
    protected string $lede = 'Chargeable items that make up a fee structure.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new FeeType();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Fee type', 'type' => 'strong'],
            ['key' => 'category', 'label' => 'Category', 'type' => 'humanize'],
            ['key' => 'is_mandatory', 'label' => 'Mandatory', 'type' => 'bool', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Fee code', 'width' => 3, 'required' => true],
            ['name' => 'name', 'label' => 'Fee name', 'width' => 9, 'required' => true],
            ['name' => 'category', 'label' => 'Category', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['tuition', 'registration', 'examination', 'library', 'activity', 'medical', 'accommodation', 'graduation', 'fine', 'other']],
            ['name' => 'is_recurring', 'label' => 'Charged every semester', 'type' => 'checkbox', 'width' => 4],
            ['name' => 'is_mandatory', 'label' => 'Mandatory', 'type' => 'checkbox', 'width' => 4],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'width' => 12],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['active', 'inactive']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code' => 'required|max:30|unique:fee_types,code,' . ($id ?? ''),
            'name' => 'required|max:120',
            'category' => 'required',
            'is_recurring' => 'nullable|integer',
            'is_mandatory' => 'nullable|integer',
            'description' => 'nullable|max:255',
            'status' => 'required|in:active,inactive',
        ];
    }
}
