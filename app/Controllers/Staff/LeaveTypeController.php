<?php
declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\ResourceController;
use App\Models\LeaveType;

final class LeaveTypeController extends ResourceController
{
    protected string $title = 'Leave Types';
    protected string $entity = 'Leave Type';
    protected string $routeBase = '/hr/leave-types';
    protected string $permission = 'hr';
    protected string $icon = 'calendar';
    protected string $lede = 'Leave categories and their annual entitlements.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new LeaveType();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Leave type', 'type' => 'strong'],
            ['key' => 'days_allowed', 'label' => 'Days allowed', 'type' => 'number', 'align' => 'center'],
            ['key' => 'is_paid', 'label' => 'Paid', 'type' => 'bool', 'align' => 'center'],
            ['key' => 'description', 'label' => 'Description'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Leave type', 'width' => 6, 'required' => true],
            ['name' => 'days_allowed', 'label' => 'Days allowed per year', 'type' => 'number', 'width' => 3, 'required' => true],
            ['name' => 'is_paid', 'label' => 'Paid leave', 'type' => 'checkbox', 'width' => 3],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'width' => 12],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'name' => 'required|max:80|unique:leave_types,name,' . ($id ?? ''),
            'days_allowed' => 'required|integer|between:0,365',
            'is_paid' => 'nullable|integer',
            'description' => 'nullable|max:255',
        ];
    }
}
