<?php
declare(strict_types=1);

namespace App\Controllers\Operations;

use App\Core\Auth;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\StockItem;
use App\Support\Lookup;

/** Consumable stores (stationery, cleaning, laboratory supplies): balances, receipts and issues. */
final class StockController extends ResourceController
{
    protected string $title = 'Stores & Stock';
    protected string $entity = 'Stock item';
    protected string $routeBase = '/assets/stock';
    protected string $permission = 'assets';
    protected bool $viewManagePermissions = true;
    protected string $icon = 'clipboard';
    protected string $lede = 'Consumable supplies. Receive stock in, issue it to departments, and see what needs reordering.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new StockItem();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $q = $this->model->query()->select('*', 'IF(quantity <= reorder_level, "reorder", "ok") AS stock_state');
        if ($request->query('state') === 'reorder') {
            $q->whereRaw('quantity <= reorder_level');
        }
        if ($request->filled('category')) {
            $q->where('category', $request->query('category'));
        }
        return $q;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'code', 'label' => 'Code', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Item', 'type' => 'strong'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'quantity', 'label' => 'In stock', 'type' => 'number', 'align' => 'end'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'reorder_level', 'label' => 'Reorder at', 'type' => 'number', 'align' => 'end'],
            ['key' => 'stock_state', 'label' => 'Level', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        $cats = array_column(Database::select('SELECT DISTINCT category FROM stock_items ORDER BY category'), 'category');
        return [
            ['name' => 'state', 'label' => 'Level', 'options' => ['reorder' => 'Needs reordering']],
            ['name' => 'category', 'label' => 'Category', 'options' => array_combine($cats, $cats) ?: []],
        ];
    }

    protected function rowActions(): array
    {
        return [['label' => 'Receive / issue stock', 'url' => '/assets/stock/{id}/movements', 'icon' => 'download']];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'code', 'label' => 'Item code', 'width' => 3, 'required' => true],
            ['name' => 'name', 'label' => 'Item', 'width' => 6, 'required' => true],
            ['name' => 'category', 'label' => 'Category', 'width' => 3, 'required' => true, 'default' => 'Stationery'],
            ['name' => 'unit', 'label' => 'Unit', 'width' => 3, 'required' => true, 'default' => 'piece', 'attrs' => 'placeholder="piece, ream, box, litre"'],
            ['name' => 'reorder_level', 'label' => 'Reorder level', 'type' => 'number', 'width' => 3, 'default' => 0, 'attrs' => 'step="0.01" min="0"'],
            ['name' => 'unit_cost', 'label' => 'Unit cost', 'type' => 'number', 'width' => 3, 'attrs' => 'step="0.01" min="0"'],
            ['name' => 'location', 'label' => 'Store location', 'width' => 3],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'code' => 'required|max:40|unique:stock_items,code,' . ($id ?? ''), 'name' => 'required|max:150', 'category' => 'required|max:80',
            'unit' => 'required|max:30', 'reorder_level' => 'nullable|numeric', 'unit_cost' => 'nullable|numeric', 'location' => 'nullable|max:120',
        ];
    }

    public function movements(Request $request, string $id): string
    {
        $this->permit('view');
        $item = $this->findOrFail($this->model, (int) $id, 'Stock item');
        return $this->view('operations.stock-movements', [
            'pageTitle'   => $item['name'],
            'item'        => $item,
            'rows'        => Database::select(
                'SELECT m.*, d.name AS department_name, CONCAT(u.first_name, " ", u.last_name) AS by_name
                   FROM stock_movements m LEFT JOIN departments d ON d.id = m.department_id LEFT JOIN users u ON u.id = m.created_by
                  WHERE m.item_id = ? ORDER BY m.id DESC LIMIT 200', [(int) $id]),
            'departments' => Lookup::departments(),
            'canManage'   => $this->allowed('manage'),
        ]);
    }

    /** Receive (in), issue (out) or correct (adjust: set the counted balance). */
    public function move(Request $request, string $id): never
    {
        $this->permit('edit');
        $this->verifyCsrf($request);
        $back = '/assets/stock/' . $id . '/movements';
        $direction = (string) $request->input('direction');
        $qty = round((float) $request->input('quantity'), 2);
        if (!in_array($direction, ['in', 'out', 'adjust'], true) || ($qty <= 0 && $direction !== 'adjust') || $qty < 0) {
            $this->error('Enter a quantity greater than zero.', $back);
        }
        $error = null;
        Database::transaction(function () use ($id, $direction, $qty, $request, &$error): void {
            $item = Database::selectOne('SELECT * FROM stock_items WHERE id = ? FOR UPDATE', [(int) $id]);
            $balance = (float) $item['quantity'];
            if ($direction === 'out' && $qty > $balance) {
                $error = 'Only ' . rtrim(rtrim(number_format($balance, 2), '0'), '.') . ' ' . $item['unit'] . ' in stock.';
                return;
            }
            $new = match ($direction) { 'in' => $balance + $qty, 'out' => $balance - $qty, default => $qty };
            Database::statement('UPDATE stock_items SET quantity = ? WHERE id = ?', [$new, (int) $id]);
            Database::statement(
                'INSERT INTO stock_movements (item_id, direction, quantity, balance_after, department_id, reference, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $id, $direction, $direction === 'adjust' ? $new - $balance : $qty, $new,
                 $request->input('department_id') ?: null, trim((string) $request->input('reference', '')) ?: null,
                 trim((string) $request->input('note', '')) ?: null, Auth::id(), date('Y-m-d H:i:s')]
            );
        });
        if ($error !== null) {
            $this->error($error, $back);
        }
        $this->success(['in' => 'Stock received.', 'out' => 'Stock issued.', 'adjust' => 'Balance corrected.'][$direction], $back);
    }
}
