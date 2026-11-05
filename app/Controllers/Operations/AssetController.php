<?php
declare(strict_types=1);

namespace App\Controllers\Operations;

use App\Core\Auth;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\Asset;
use App\Support\Lookup;

/** The asset register: equipment, furniture and vehicles, where they are, who holds them, their upkeep. */
final class AssetController extends ResourceController
{
    protected string $title = 'Asset Register';
    protected string $entity = 'Asset';
    protected string $routeBase = '/assets';
    protected string $permission = 'assets';
    protected bool $viewManagePermissions = true;
    protected string $icon = 'building';
    protected string $lede = 'University property: computers, projectors, furniture, laboratory equipment and vehicles.';
    protected string $defaultSort = 'tag';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new Asset();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $q = $this->model->query('a')
            ->select('a.*', 'r.code AS room_code', 'd.name AS department_name', 'CONCAT(u.first_name, " ", u.last_name) AS custodian_name')
            ->leftJoin('rooms r', 'r.id = a.room_id')
            ->leftJoin('departments d', 'd.id = a.department_id')
            ->leftJoin('staff st', 'st.id = a.custodian_id')
            ->leftJoin('users u', 'u.id = st.user_id');
        foreach (['category', 'status', 'department_id'] as $f) {
            if ($request->filled($f)) {
                $q->where('a.' . $f, $request->query($f));
            }
        }
        return $q;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'tag', 'label' => 'Tag', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Asset', 'type' => 'strong'],
            ['key' => 'category', 'label' => 'Category', 'type' => 'humanize'],
            ['key' => 'room_code', 'label' => 'Location'],
            ['key' => 'custodian_name', 'label' => 'Custodian'],
            ['key' => 'purchase_cost', 'label' => 'Cost', 'type' => 'money', 'align' => 'end'],
            ['key' => 'asset_condition', 'label' => 'Condition', 'type' => 'humanize'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        return [
            ['name' => 'category', 'label' => 'Category', 'options' => array_combine(self::CATEGORIES, array_map('humanize', self::CATEGORIES))],
            ['name' => 'status', 'label' => 'Status', 'options' => array_combine(self::STATUSES, array_map('humanize', self::STATUSES))],
            ['name' => 'department_id', 'label' => 'Department', 'options' => Lookup::departments()],
        ];
    }

    protected function rowActions(): array
    {
        return [['label' => 'Maintenance history', 'url' => '/assets/{id}/maintenance', 'icon' => 'settings']];
    }

    private const CATEGORIES = ['computer', 'printer', 'projector', 'furniture', 'laboratory', 'vehicle', 'network', 'electrical', 'books', 'other'];
    private const STATUSES   = ['in_use', 'in_store', 'under_repair', 'disposed', 'lost'];

    protected function fields(): array
    {
        return [
            ['name' => 'tag', 'label' => 'Asset tag', 'width' => 4, 'required' => true, 'attrs' => 'placeholder="e.g. BBIU-IT-0001"'],
            ['name' => 'name', 'label' => 'Description', 'width' => 8, 'required' => true],
            ['name' => 'category', 'label' => 'Category', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => self::CATEGORIES],
            ['name' => 'serial_number', 'label' => 'Serial number', 'width' => 4],
            ['name' => 'supplier', 'label' => 'Supplier', 'width' => 4],
            ['name' => 'room_id', 'label' => 'Location (room)', 'type' => 'select', 'width' => 4, 'options' => Lookup::rooms()],
            ['name' => 'department_id', 'label' => 'Department', 'type' => 'select', 'width' => 4, 'options' => Lookup::departments()],
            ['name' => 'custodian_id', 'label' => 'Custodian', 'type' => 'select', 'width' => 4, 'options' => Lookup::staff()],
            ['name' => 'purchase_date', 'label' => 'Purchase date', 'type' => 'date', 'width' => 3],
            ['name' => 'purchase_cost', 'label' => 'Purchase cost', 'type' => 'number', 'width' => 3, 'attrs' => 'step="0.01" min="0"'],
            ['name' => 'warranty_until', 'label' => 'Warranty until', 'type' => 'date', 'width' => 3],
            ['name' => 'asset_condition', 'label' => 'Condition', 'type' => 'enum', 'width' => 3, 'required' => true, 'values' => ['new', 'good', 'fair', 'poor', 'broken']],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => self::STATUSES],
            ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'width' => 8],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'tag' => 'required|max:40|unique:assets,tag,' . ($id ?? ''),
            'name' => 'required|max:150',
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
            'serial_number' => 'nullable|max:100', 'supplier' => 'nullable|max:150',
            'room_id' => 'nullable|integer', 'department_id' => 'nullable|integer', 'custodian_id' => 'nullable|integer',
            'purchase_date' => 'nullable|date', 'purchase_cost' => 'nullable|numeric', 'warranty_until' => 'nullable|date',
            'asset_condition' => 'required|in:new,good,fair,poor,broken',
            'status' => 'required|in:' . implode(',', self::STATUSES),
            'notes' => 'nullable',
        ];
    }

    /** Maintenance and repair history of one asset. */
    public function maintenance(Request $request, string $id): string
    {
        $this->permit('view');
        $asset = $this->findOrFail($this->model, (int) $id, 'Asset');
        return $this->view('operations.asset-maintenance', [
            'pageTitle' => 'Maintenance - ' . $asset['tag'],
            'asset'     => $asset,
            'rows'      => Database::select(
                'SELECT m.*, CONCAT(u.first_name, " ", u.last_name) AS by_name FROM asset_maintenance m LEFT JOIN users u ON u.id = m.created_by
                  WHERE m.asset_id = ? ORDER BY m.performed_on DESC', [(int) $id]),
            'canManage' => $this->allowed('manage'),
        ]);
    }

    public function addMaintenance(Request $request, string $id): never
    {
        $this->permit('edit');
        $this->verifyCsrf($request);
        $this->findOrFail($this->model, (int) $id, 'Asset');
        $data = $this->validate($request, ['performed_on' => 'required|date', 'description' => 'required|max:255', 'cost' => 'nullable|numeric', 'vendor' => 'nullable|max:150']);
        Database::statement(
            'INSERT INTO asset_maintenance (asset_id, performed_on, description, cost, vendor, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $id, $data['performed_on'], $data['description'], ($data['cost'] ?? '') === '' ? null : $data['cost'],
             ($data['vendor'] ?? '') ?: null, Auth::id(), date('Y-m-d H:i:s')]
        );
        if ($request->input('set_status')) {
            Database::statement('UPDATE assets SET status = ? WHERE id = ?', [$request->input('set_status') === 'under_repair' ? 'under_repair' : 'in_use', (int) $id]);
        }
        $this->success('Maintenance recorded.', '/assets/' . $id . '/maintenance');
    }
}
