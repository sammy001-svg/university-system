<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\ResourceController;
use App\Models\Building;
use App\Models\Campus;

final class BuildingController extends ResourceController
{
    protected string $title = 'Buildings';
    protected string $entity = 'Building';
    protected string $routeBase = '/admin/buildings';
    protected string $permission = 'settings';
    protected string $icon = 'building';
    protected string $lede = 'Blocks and buildings that contain teaching rooms.';
    protected string $defaultSort = 'code';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Building();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Building', 'type' => 'strong'],
            ['key' => 'floors', 'label' => 'Floors', 'type' => 'number', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Building code', 'width' => 4, 'required' => true],
            ['name' => 'name', 'label' => 'Building name', 'width' => 8, 'required' => true],
            ['name' => 'campus_id', 'label' => 'Campus', 'type' => 'select', 'width' => 6, 'options' => (new Campus())->options()],
            ['name' => 'floors', 'label' => 'Number of floors', 'type' => 'number', 'width' => 3],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => ['active', 'inactive']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code' => 'required|max:20|unique:buildings,code,' . ($id ?? ''),
            'name' => 'required|max:120',
            'campus_id' => 'nullable|integer',
            'floors' => 'nullable|integer|between:1,60',
            'status' => 'required|in:active,inactive',
        ];
    }
}
