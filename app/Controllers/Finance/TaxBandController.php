<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\ResourceController;
use App\Models\TaxBand;

/**
 * The income-tax schedule the payroll applies. Each band charges its rate on
 * the pay above "from", on top of the fixed amount already due on the bands
 * below it. The installed bands are Liberia's statutory personal income tax
 * schedule; the Finance Office must confirm them before the first run.
 */
final class TaxBandController extends ResourceController
{
    protected string $title = 'Income Tax Bands';
    protected string $entity = 'Tax Band';
    protected string $routeBase = '/finance/tax-bands';
    protected string $permission = 'payroll';
    protected string $icon = 'money';
    protected string $lede = 'Confirm these against the current tax schedule before running payroll. Annual bands are applied to twelve months of pay.';
    protected string $defaultSort = 'sort_order';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new TaxBand();
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Band', 'type' => 'strong'],
            ['key' => 'band_from', 'label' => 'From', 'type' => 'number', 'align' => 'right'],
            ['key' => 'band_to', 'label' => 'To', 'type' => 'number', 'align' => 'right'],
            ['key' => 'rate', 'label' => 'Rate %', 'type' => 'number', 'align' => 'right'],
            ['key' => 'fixed_amount', 'label' => 'Tax on lower bands', 'type' => 'number', 'align' => 'right'],
            ['key' => 'period', 'label' => 'Period', 'type' => 'humanize'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Band', 'width' => 12, 'required' => true],
            ['name' => 'band_from', 'label' => 'Pay from', 'type' => 'number', 'step' => '0.01', 'width' => 4, 'required' => true],
            ['name' => 'band_to', 'label' => 'Pay up to', 'type' => 'number', 'step' => '0.01', 'width' => 4,
             'hint' => 'Leave empty for the top band.'],
            ['name' => 'rate', 'label' => 'Rate on pay within this band (%)', 'type' => 'number', 'step' => '0.01', 'width' => 4, 'required' => true],
            ['name' => 'fixed_amount', 'label' => 'Tax already due on the bands below', 'type' => 'number', 'step' => '0.01', 'width' => 4],
            ['name' => 'period', 'label' => 'The figures above are', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['annual', 'monthly']],
            ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'width' => 4],
            ['name' => 'effective_from', 'label' => 'In force from', 'type' => 'date', 'width' => 4],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => ['active', 'inactive']],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'name'           => 'required|max:80',
            'band_from'      => 'required|numeric|min:0',
            'band_to'        => 'nullable|numeric|min:0',
            'rate'           => 'required|numeric|between:0,100',
            'fixed_amount'   => 'nullable|numeric|min:0',
            'period'         => 'required|in:annual,monthly',
            'sort_order'     => 'nullable|integer|between:0,255',
            'effective_from' => 'nullable|date',
            'status'         => 'required|in:active,inactive',
        ];
    }
}
