<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\ResourceController;
use App\Models\TuitionRate;

final class TuitionRateController extends ResourceController
{
    protected string $title = 'Tuition Rates';
    protected string $entity = 'Tuition Rate';
    protected string $routeBase = '/finance/tuition-rates';
    protected string $permission = 'finance';
    protected string $icon = 'money';
    protected string $lede = 'Tuition is charged per credit hour of each registered course, at the rate of the course\'s band.';
    protected string $defaultSort = 'rate_per_credit';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new TuitionRate();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Band', 'type' => 'strong'],
            ['key' => 'rate_per_credit', 'label' => 'Per credit hour', 'type' => 'money', 'align' => 'end'],
            ['key' => 'description', 'label' => 'Applies to', 'limit' => 80],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Code', 'width' => 3, 'required' => true, 'placeholder' => 'e.g. SCIENCE'],
            ['name' => 'name', 'label' => 'Band name', 'width' => 6, 'required' => true],
            ['name' => 'rate_per_credit', 'label' => 'Rate per credit hour', 'type' => 'number', 'width' => 3, 'required' => true, 'min' => 0, 'step' => '0.01'],
            ['name' => 'description', 'label' => 'Applies to', 'type' => 'textarea', 'width' => 12, 'rows' => 2],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['active', 'inactive']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code'            => 'required|max:20|unique:tuition_rates,code,' . ($id ?? ''),
            'name'            => 'required|max:120',
            'rate_per_credit' => 'required|numeric',
            'description'     => 'nullable|max:255',
            'status'          => 'required|in:active,inactive',
        ];
    }
}
