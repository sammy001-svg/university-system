<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\ResourceController;
use App\Models\Campus;

final class CampusController extends ResourceController
{
    protected string $title = 'Campuses';
    protected string $entity = 'Campus';
    protected string $routeBase = '/admin/campuses';
    protected string $permission = 'settings';
    protected string $icon = 'building';
    protected string $lede = 'Physical campuses operated by the institution.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Campus();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Campus', 'type' => 'strong'],
            ['key' => 'city', 'label' => 'City'],
            ['key' => 'phone', 'label' => 'Phone'],
            ['key' => 'is_main', 'label' => 'Main', 'type' => 'bool', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Campus code', 'width' => 4, 'required' => true],
            ['name' => 'name', 'label' => 'Campus name', 'width' => 8, 'required' => true],
            ['name' => 'address', 'label' => 'Postal address', 'width' => 6],
            ['name' => 'city', 'label' => 'City / town', 'width' => 3],
            ['name' => 'country', 'label' => 'Country', 'width' => 3],
            ['name' => 'phone', 'label' => 'Phone', 'width' => 4],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'width' => 4],
            ['name' => 'is_main', 'label' => 'Main campus', 'type' => 'checkbox', 'width' => 4],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['active', 'inactive']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code' => 'required|max:20|unique:campuses,code,' . ($id ?? ''),
            'name' => 'required|max:120',
            'address' => 'nullable|max:255',
            'city' => 'nullable|max:80',
            'country' => 'nullable|max:80',
            'phone' => 'nullable|phone',
            'email' => 'nullable|email|max:120',
            'is_main' => 'nullable|integer',
            'status' => 'required|in:active,inactive',
        ];
    }
}
