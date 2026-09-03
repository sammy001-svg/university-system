<?php
declare(strict_types=1);

namespace App\Controllers\Hostel;

use App\Core\ResourceController;
use App\Models\Hostel;
use App\Models\HostelRoom;

final class RoomController extends ResourceController
{
    protected string $title = 'Hostel Rooms';
    protected string $entity = 'Hostel Room';
    protected string $routeBase = '/hostel/rooms';
    protected string $permission = 'hostel';
    protected string $icon = 'bed';
    protected string $lede = 'Rooms, bed capacity and accommodation charges.';
    protected string $defaultSort = 'room_number';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new HostelRoom();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'room_number', 'label' => 'Room', 'type' => 'strong'],
            ['key' => 'room_type', 'label' => 'Type', 'type' => 'humanize'],
            ['key' => 'capacity', 'label' => 'Beds', 'type' => 'number', 'align' => 'center'],
            ['key' => 'occupied', 'label' => 'Occupied', 'type' => 'number', 'align' => 'center'],
            ['key' => 'fee_per_semester', 'label' => 'Fee', 'type' => 'money'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'hostel_id', 'label' => 'Hall of residence', 'type' => 'select', 'width' => 6, 'required' => true, 'options' => (new Hostel())->options()],
            ['name' => 'room_number', 'label' => 'Room number', 'width' => 3, 'required' => true],
            ['name' => 'floor', 'label' => 'Floor', 'type' => 'number', 'width' => 3],
            ['name' => 'room_type', 'label' => 'Room type', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => ['single', 'double', 'triple', 'dormitory']],
            ['name' => 'capacity', 'label' => 'Bed capacity', 'type' => 'number', 'width' => 3, 'required' => true],
            ['name' => 'fee_per_semester', 'label' => 'Fee per semester', 'type' => 'number', 'width' => 3],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => ['available', 'full', 'maintenance', 'reserved']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'hostel_id' => 'required|integer|exists:hostels,id',
            'room_number' => 'required|max:20',
            'floor' => 'nullable|integer|between:0,30',
            'room_type' => 'required',
            'capacity' => 'required|integer|between:1,20',
            'fee_per_semester' => 'nullable|numeric|min:0',
            'status' => 'required|in:available,full,maintenance,reserved',
        ];
    }
}
