<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\ResourceController;
use App\Models\Building;
use App\Models\Room;

final class RoomController extends ResourceController
{
    protected string $title = 'Rooms';
    protected string $entity = 'Room';
    protected string $routeBase = '/admin/rooms';
    protected string $permission = 'settings';
    protected string $icon = 'building';
    protected string $lede = 'Lecture halls, laboratories and examination venues.';
    protected string $defaultSort = 'code';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Room();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Room', 'type' => 'strong'],
            ['key' => 'room_type', 'label' => 'Type', 'type' => 'humanize'],
            ['key' => 'capacity', 'label' => 'Capacity', 'type' => 'number', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Room code', 'width' => 4, 'required' => true],
            ['name' => 'name', 'label' => 'Room name', 'width' => 8, 'required' => true],
            ['name' => 'building_id', 'label' => 'Building', 'type' => 'select', 'width' => 6, 'options' => (new Building())->options()],
            ['name' => 'room_type', 'label' => 'Room type', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => ['lecture_hall', 'laboratory', 'tutorial', 'exam_hall', 'office', 'library', 'other']],
            ['name' => 'capacity', 'label' => 'Seating capacity', 'type' => 'number', 'width' => 3, 'required' => true],
            ['name' => 'floor', 'label' => 'Floor', 'type' => 'number', 'width' => 3],
            ['name' => 'has_projector', 'label' => 'Projector fitted', 'type' => 'checkbox', 'width' => 3],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => ['available', 'maintenance', 'unavailable']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code' => 'required|max:30|unique:rooms,code,' . ($id ?? ''),
            'name' => 'required|max:120',
            'building_id' => 'nullable|integer',
            'room_type' => 'required',
            'capacity' => 'required|integer|between:1,2000',
            'floor' => 'nullable|integer|between:0,60',
            'has_projector' => 'nullable|integer',
            'status' => 'required|in:available,maintenance,unavailable',
        ];
    }
}
