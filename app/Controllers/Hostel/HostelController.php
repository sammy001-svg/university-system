<?php
declare(strict_types=1);

namespace App\Controllers\Hostel;

use App\Core\ResourceController;
use App\Models\Campus;
use App\Models\Hostel;

final class HostelController extends ResourceController
{
    protected string $title = 'Halls of Residence';
    protected string $entity = 'Hostel';
    protected string $routeBase = '/hostel/hostels';
    protected string $permission = 'hostel';
    protected string $icon = 'bed';
    protected string $lede = 'Student accommodation blocks and their wardens.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Hostel();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Hall', 'type' => 'strong'],
            ['key' => 'gender', 'label' => 'Occupancy', 'type' => 'humanize'],
            ['key' => 'total_rooms', 'label' => 'Rooms', 'type' => 'number', 'align' => 'center'],
            ['key' => 'location', 'label' => 'Location'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Hall code', 'width' => 4, 'required' => true],
            ['name' => 'name', 'label' => 'Hall name', 'width' => 8, 'required' => true],
            ['name' => 'campus_id', 'label' => 'Campus', 'type' => 'select', 'width' => 4, 'options' => (new Campus())->options()],
            ['name' => 'gender', 'label' => 'Occupancy', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['male', 'female', 'mixed']],
            ['name' => 'total_rooms', 'label' => 'Total rooms', 'type' => 'number', 'width' => 4],
            ['name' => 'location', 'label' => 'Location on campus', 'width' => 6],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['active', 'inactive', 'maintenance']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code' => 'required|max:20|unique:hostels,code,' . ($id ?? ''),
            'name' => 'required|max:120',
            'campus_id' => 'nullable|integer',
            'gender' => 'required|in:male,female,mixed',
            'total_rooms' => 'nullable|integer|min:0',
            'location' => 'nullable|max:150',
            'status' => 'required|in:active,inactive,maintenance',
        ];
    }
}
