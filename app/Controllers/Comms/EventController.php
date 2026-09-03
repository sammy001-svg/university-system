<?php
declare(strict_types=1);

namespace App\Controllers\Comms;

use App\Core\ResourceController;
use App\Models\Event;

final class EventController extends ResourceController
{
    protected string $title = 'Events';
    protected string $entity = 'Event';
    protected string $routeBase = '/events';
    protected string $permission = 'events';
    protected string $icon = 'calendar';
    protected string $lede = 'University calendar of ceremonies, meetings and activities.';
    protected string $defaultSort = 'start_datetime';
    protected string $defaultDir = 'desc';

    public function __construct()
    {
        $this->model = new Event();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Event', 'type' => 'strong'],
            ['key' => 'event_type', 'label' => 'Type', 'type' => 'humanize'],
            ['key' => 'start_datetime', 'label' => 'Starts', 'type' => 'datetime'],
            ['key' => 'venue', 'label' => 'Venue'],
            ['key' => 'organizer', 'label' => 'Organiser'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'title', 'label' => 'Event title', 'width' => 8, 'required' => true],
            ['name' => 'event_type', 'label' => 'Event type', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['academic', 'sports', 'cultural', 'graduation', 'orientation', 'meeting', 'holiday', 'other']],
            ['name' => 'start_datetime', 'label' => 'Starts', 'type' => 'datetime-local', 'width' => 4, 'required' => true],
            ['name' => 'end_datetime', 'label' => 'Ends', 'type' => 'datetime-local', 'width' => 4],
            ['name' => 'venue', 'label' => 'Venue', 'width' => 4],
            ['name' => 'organizer', 'label' => 'Organised by', 'width' => 6],
            ['name' => 'is_public', 'label' => 'Visible to everyone', 'type' => 'checkbox', 'width' => 6],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'width' => 12],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'title' => 'required|max:200',
            'event_type' => 'required',
            'start_datetime' => 'required|date',
            'end_datetime' => 'nullable|date',
            'venue' => 'nullable|max:180',
            'organizer' => 'nullable|max:180',
            'is_public' => 'nullable|integer',
            'description' => 'nullable|max:3000',
        ];
    }
}
