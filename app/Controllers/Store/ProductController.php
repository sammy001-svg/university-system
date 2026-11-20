<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Core\Auth;
use App\Core\Database;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\StoreProduct;

/**
 * Store products: goods sold to students and staff (uniforms, books, stationery,
 * branded items). Prices, stock received and stock counts. Sales reduce stock
 * through App\Controllers\Store\SaleController.
 */
final class ProductController extends ResourceController
{
    protected string $title = 'Store Products';
    protected string $entity = 'Product';
    protected string $routeBase = '/store/products';
    protected string $permission = 'store';
    protected bool $viewManagePermissions = true;
    protected string $icon = 'clipboard';
    protected string $lede = 'Items for sale to students and staff. Set prices, receive stock and see what needs reordering.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public function __construct()
    {
        $this->model = new StoreProduct();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $q = $this->model->query()->select(
            '*',
            'CASE WHEN is_active = 0 THEN "inactive" WHEN quantity <= 0 THEN "out_of_stock" WHEN quantity <= reorder_level THEN "reorder" ELSE "available" END AS stock_state'
        );
        $state = (string) $request->query('state', '');
        if ($state === 'reorder') {
            $q->whereRaw('quantity <= reorder_level');
        } elseif ($state === 'out') {
            $q->whereRaw('quantity <= 0');
        } elseif ($state === 'inactive') {
            $q->where('is_active', 0);
        }
        if ($request->filled('category')) {
            $q->where('category', $request->query('category'));
        }
        return $q;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'sku', 'label' => 'SKU', 'type' => 'code'],
            ['key' => 'name', 'label' => 'Product', 'type' => 'strong'],
            ['key' => 'category', 'label' => 'Category'],
            ['key' => 'price', 'label' => 'Price', 'type' => 'money', 'align' => 'end'],
            ['key' => 'quantity', 'label' => 'In stock', 'type' => 'number', 'align' => 'end'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'stock_state', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        $cats = array_column(Database::select('SELECT DISTINCT category FROM store_products ORDER BY category'), 'category');
        return [
            ['name' => 'state', 'label' => 'Status', 'options' => ['reorder' => 'Needs reordering', 'out' => 'Out of stock', 'inactive' => 'Not for sale']],
            ['name' => 'category', 'label' => 'Category', 'options' => array_combine($cats, $cats) ?: []],
        ];
    }

    protected function rowActions(): array
    {
        return [['label' => 'Stock & history', 'url' => '/store/products/{id}/stock', 'icon' => 'download']];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'sku', 'label' => 'SKU / item code', 'width' => 3, 'required' => true],
            ['name' => 'name', 'label' => 'Product name', 'width' => 6, 'required' => true],
            ['name' => 'category', 'label' => 'Category', 'width' => 3, 'required' => true, 'default' => 'General',
             'attrs' => 'placeholder="Uniform, Books, Stationery, Merchandise"'],
            ['name' => 'price', 'label' => 'Selling price', 'type' => 'number', 'width' => 3, 'required' => true, 'attrs' => 'step="0.01" min="0"'],
            ['name' => 'cost_price', 'label' => 'Cost price', 'type' => 'number', 'width' => 3, 'attrs' => 'step="0.01" min="0"', 'help' => 'Optional, for margin reporting.'],
            ['name' => 'unit', 'label' => 'Unit', 'width' => 3, 'required' => true, 'default' => 'piece', 'attrs' => 'placeholder="piece, pack, set"'],
            ['name' => 'reorder_level', 'label' => 'Reorder level', 'type' => 'number', 'width' => 3, 'default' => 0, 'attrs' => 'step="0.01" min="0"'],
            ['name' => 'description', 'label' => 'Description', 'width' => 9],
            ['name' => 'is_active', 'label' => 'For sale', 'type' => 'checkbox', 'width' => 3, 'default' => '1', 'checkboxLabel' => 'Available at the counter'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'sku' => 'required|max:40|unique:store_products,sku,' . ($id ?? ''), 'name' => 'required|max:150', 'category' => 'required|max:80',
            'price' => 'required|numeric', 'cost_price' => 'nullable|numeric', 'unit' => 'required|max:30',
            'reorder_level' => 'nullable|numeric', 'description' => 'nullable|max:255', 'is_active' => 'nullable|in:0,1',
        ];
    }

    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        $data['is_active'] = (int) ($data['is_active'] ?? 0) === 1 ? 1 : 0;
        $data['price'] = max(0, round((float) $data['price'], 2));
        $data['reorder_level'] = max(0, (float) ($data['reorder_level'] ?? 0));
        if (($data['cost_price'] ?? '') === '') {
            $data['cost_price'] = null;
        }
        // Stock is never typed in here: it moves through receipts, counts and sales.
        unset($data['quantity']);
        return $data;
    }

    protected function guardDelete(array $record): ?string
    {
        $sold = (int) Database::scalar('SELECT COUNT(*) FROM store_sale_items WHERE product_id = ?', [(int) $record['id']]);
        return $sold > 0 ? 'This product has sales on record. Untick "For sale" instead of deleting it.' : null;
    }

    public function stock(Request $request, string $id): string
    {
        $this->permit('view');
        $product = $this->findOrFail($this->model, (int) $id, 'Product');
        return $this->view('store.product-stock', [
            'pageTitle' => $product['name'],
            'product'   => $product,
            'rows'      => Database::select(
                'SELECT m.*, s.receipt_number, CONCAT(u.first_name, " ", u.last_name) AS by_name
                   FROM store_stock_movements m
                   LEFT JOIN store_sales s ON s.id = m.sale_id
                   LEFT JOIN users u ON u.id = m.created_by
                  WHERE m.product_id = ? ORDER BY m.id DESC LIMIT 200', [(int) $id]),
            'soldQty'   => (float) Database::scalar(
                'SELECT COALESCE(SUM(i.quantity), 0) FROM store_sale_items i JOIN store_sales s ON s.id = i.sale_id
                  WHERE i.product_id = ? AND s.status = "completed"', [(int) $id]),
            'canManage' => $this->allowed('manage'),
        ]);
    }

    /** Receive stock (in) or record a stock count (adjust: set the counted balance). */
    public function move(Request $request, string $id): never
    {
        $this->permit('edit');
        $this->verifyCsrf($request);
        $back = '/store/products/' . $id . '/stock';
        $direction = (string) $request->input('direction');
        $qty = round((float) $request->input('quantity'), 2);
        if (!in_array($direction, ['in', 'adjust'], true) || $qty < 0 || ($direction === 'in' && $qty <= 0)) {
            $this->error('Enter a quantity greater than zero.', $back);
        }
        Database::transaction(function () use ($id, $direction, $qty, $request): void {
            $product = Database::selectOne('SELECT * FROM store_products WHERE id = ? FOR UPDATE', [(int) $id]);
            if ($product === null) {
                $this->abort(404, 'Product not found.');
            }
            $balance = (float) $product['quantity'];
            $new = $direction === 'in' ? $balance + $qty : $qty;
            Database::statement('UPDATE store_products SET quantity = ? WHERE id = ?', [$new, (int) $id]);
            Database::statement(
                'INSERT INTO store_stock_movements (product_id, direction, quantity, balance_after, reference, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $id, $direction, $direction === 'adjust' ? $new - $balance : $qty, $new,
                 trim((string) $request->input('reference', '')) ?: null,
                 trim((string) $request->input('note', '')) ?: null, Auth::id(), date('Y-m-d H:i:s')]
            );
        });
        $this->success($direction === 'in' ? 'Stock received.' : 'Balance corrected.', $back);
    }
}

