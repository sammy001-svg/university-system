<?php
declare(strict_types=1);

namespace App\Controllers\Store;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;

/** Store counter: sell products to students, staff and walk-in customers, issue receipts, void sales. */
final class SaleController extends Controller
{
    private const METHODS = ['cash', 'mpesa', 'card', 'bank_transfer'];

    public function index(Request $request): string
    {
        $this->authorize('store.view');
        $from   = $this->dateOr((string) $request->query('from', ''), date('Y-m-01'));
        $to     = $this->dateOr((string) $request->query('to', ''), date('Y-m-d'));
        $status = (string) $request->query('status', '');
        $type   = (string) $request->query('customer_type', '');
        $q      = trim((string) $request->query('q', ''));

        $where  = ['s.sold_at >= ?', 's.sold_at < DATE_ADD(?, INTERVAL 1 DAY)'];
        $params = [$from, $to];
        if (in_array($status, ['completed', 'voided'], true)) {
            $where[] = 's.status = ?';
            $params[] = $status;
        }
        if (in_array($type, ['student', 'staff', 'walk_in'], true)) {
            $where[] = 's.customer_type = ?';
            $params[] = $type;
        }
        if ($q !== '') {
            $where[] = '(s.receipt_number LIKE ? OR s.customer_name LIKE ? OR st.admission_number LIKE ? OR sf.staff_number LIKE ?)';
            array_push($params, "%{$q}%", "%{$q}%", "%{$q}%", "%{$q}%");
        }
        $sql = ' FROM store_sales s
                 LEFT JOIN students st ON st.id = s.student_id
                 LEFT JOIN staff sf    ON sf.id = s.staff_id
                 LEFT JOIN users u     ON u.id = s.sold_by
                WHERE ' . implode(' AND ', $where);

        $sales = Database::select(
            'SELECT s.*, st.admission_number, sf.staff_number, CONCAT(u.first_name, " ", u.last_name) AS sold_by_name,
                    (SELECT COALESCE(SUM(i.quantity), 0) FROM store_sale_items i WHERE i.sale_id = s.id) AS item_count'
            . $sql . ' ORDER BY s.sold_at DESC, s.id DESC LIMIT 300',
            $params
        );
        $totals = Database::selectOne(
            'SELECT COUNT(*) AS sales, COALESCE(SUM(s.total), 0) AS revenue,
                    COALESCE(SUM(CASE WHEN s.payment_method = "cash" THEN s.total ELSE 0 END), 0) AS cash'
            . $sql . ' AND s.status = "completed"',
            $params
        ) ?? ['sales' => 0, 'revenue' => 0, 'cash' => 0];

        return $this->view('store.sales.index', [
            'pageTitle' => 'Store Sales',
            'sales'     => $sales,
            'totals'    => $totals,
            'filters'   => ['from' => $from, 'to' => $to, 'status' => $status, 'customer_type' => $type, 'q' => $q],
        ]);
    }

    public function create(Request $request): string
    {
        $this->authorize('store.sell');
        return $this->view('store.sales.create', [
            'pageTitle' => 'Point of Sale',
            'products'  => Database::select(
                'SELECT id, sku, name, category, unit, price, quantity FROM store_products WHERE is_active = 1 ORDER BY name'
            ),
            'students'  => Database::select(
                "SELECT s.id, s.admission_number AS code, CONCAT(u.first_name, ' ', u.last_name) AS name
                   FROM students s JOIN users u ON u.id = s.user_id
                  WHERE s.status = 'active' ORDER BY u.last_name, u.first_name"
            ),
            'staff'     => Database::select(
                "SELECT s.id, s.staff_number AS code, CONCAT(u.first_name, ' ', u.last_name) AS name
                   FROM staff s JOIN users u ON u.id = s.user_id
                  ORDER BY u.last_name, u.first_name"
            ),
            'methods'   => self::METHODS,
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('store.sell');
        $this->verifyCsrf($request);
        $back = '/store/sales/create';

        $type = (string) $request->input('customer_type', 'walk_in');
        if (!in_array($type, ['student', 'staff', 'walk_in'], true)) {
            $this->fail('Choose who the customer is.', $request, $back);
        }
        $method = (string) $request->input('payment_method', 'cash');
        if (!in_array($method, self::METHODS, true)) {
            $this->fail('Choose a payment method.', $request, $back);
        }

        // Customer
        $studentId = $staffId = null;
        $customerName = trim((string) $request->input('customer_name', ''));
        if ($type === 'student') {
            $row = Database::selectOne(
                "SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) AS name FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?",
                [$request->int('student_id')]
            );
            if ($row === null) {
                $this->fail('Select the student from the list.', $request, $back);
            }
            [$studentId, $customerName] = [(int) $row['id'], $row['name']];
        } elseif ($type === 'staff') {
            $row = Database::selectOne(
                "SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) AS name FROM staff s JOIN users u ON u.id = s.user_id WHERE s.id = ?",
                [$request->int('staff_id')]
            );
            if ($row === null) {
                $this->fail('Select the staff member from the list.', $request, $back);
            }
            [$staffId, $customerName] = [(int) $row['id'], $row['name']];
        }
        $customerName = mb_substr($customerName !== '' ? $customerName : 'Walk-in customer', 0, 150);

        // Basket: product_id[] and quantity[] posted side by side; repeated products are merged.
        $ids  = $request->array('product_id');
        $qtys = $request->array('quantity');
        $basket = [];
        foreach ($ids as $i => $pid) {
            $pid = (int) $pid;
            $qty = round((float) ($qtys[$i] ?? 0), 2);
            if ($pid > 0 && $qty > 0) {
                $basket[$pid] = ($basket[$pid] ?? 0) + $qty;
            }
        }
        if ($basket === []) {
            $this->fail('Add at least one product to the sale.', $request, $back);
        }

        $discount  = max(0, round((float) $request->input('discount', 0), 2));
        $tendered  = $request->filled('amount_tendered') ? round((float) $request->input('amount_tendered'), 2) : null;
        $reference = mb_substr(trim((string) $request->input('reference', '')), 0, 80) ?: null;
        $note      = mb_substr(trim((string) $request->input('note', '')), 0, 255) ?: null;

        $error = null;
        $saleId = 0;
        Database::transaction(function () use (
            $basket, $type, $studentId, $staffId, $customerName, $method, $discount, $tendered, $reference, $note, &$error, &$saleId
        ): void {
            $placeholders = implode(',', array_fill(0, count($basket), '?'));
            $products = [];
            foreach (Database::select(
                "SELECT * FROM store_products WHERE id IN ({$placeholders}) FOR UPDATE", array_keys($basket)
            ) as $p) {
                $products[(int) $p['id']] = $p;
            }

            $lines = [];
            $subtotal = 0.0;
            foreach ($basket as $pid => $qty) {
                $p = $products[$pid] ?? null;
                if ($p === null || (int) $p['is_active'] !== 1) {
                    $error = 'One of the products is no longer for sale. Refresh the page and try again.';
                    return;
                }
                if ($qty > (float) $p['quantity']) {
                    $error = 'Only ' . $this->qty($p['quantity']) . ' ' . $p['unit'] . ' of ' . $p['name'] . ' in stock.';
                    return;
                }
                // Prices always come from the database, never from the browser.
                $lineTotal = round((float) $p['price'] * $qty, 2);
                $subtotal += $lineTotal;
                $lines[] = [$p, $qty, $lineTotal];
            }
            $subtotal = round($subtotal, 2);
            if ($discount > $subtotal) {
                $error = 'The discount cannot be more than the sale amount.';
                return;
            }
            $total = round($subtotal - $discount, 2);
            $change = null;
            if ($method === 'cash') {
                $tendered ??= $total;
                if ($tendered < $total) {
                    $error = 'Cash received (' . money($tendered) . ') is less than the total (' . money($total) . ').';
                    return;
                }
                $change = round($tendered - $total, 2);
            } else {
                $tendered = null;
            }

            $now = date('Y-m-d H:i:s');
            Database::statement(
                'INSERT INTO store_sales (customer_type, student_id, staff_id, customer_name, subtotal, discount, total,
                                          payment_method, reference, amount_tendered, change_due, note, status, sold_by, sold_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "completed", ?, ?)',
                [$type, $studentId, $staffId, $customerName, $subtotal, $discount, $total,
                 $method, $reference, $tendered, $change, $note, Auth::id(), $now]
            );
            $saleId = Database::lastInsertId();
            $receipt = 'STR-' . date('ymd') . '-' . str_pad((string) $saleId, 5, '0', STR_PAD_LEFT);
            Database::statement('UPDATE store_sales SET receipt_number = ? WHERE id = ?', [$receipt, $saleId]);

            foreach ($lines as [$p, $qty, $lineTotal]) {
                Database::statement(
                    'INSERT INTO store_sale_items (sale_id, product_id, sku, product_name, unit_price, quantity, line_total)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$saleId, (int) $p['id'], $p['sku'], $p['name'], $p['price'], $qty, $lineTotal]
                );
                $balance = round((float) $p['quantity'] - $qty, 2);
                Database::statement('UPDATE store_products SET quantity = ? WHERE id = ?', [$balance, (int) $p['id']]);
                Database::statement(
                    'INSERT INTO store_stock_movements (product_id, direction, quantity, balance_after, sale_id, reference, created_by, created_at)
                     VALUES (?, "sale", ?, ?, ?, ?, ?, ?)',
                    [(int) $p['id'], $qty, $balance, $saleId, $receipt, Auth::id(), $now]
                );
            }
            Audit::created('Store sale', $saleId, ['receipt' => $receipt, 'total' => $total, 'customer' => $customerName], 'store');
        });

        if ($error !== null) {
            $this->fail($error, $request, $back);
        }
        Session::clearOldInput();
        $this->success('Sale recorded.', '/store/sales/' . $saleId);
    }

    public function show(Request $request, string $id): string
    {
        $this->authorize('store.view');
        $sale = Database::selectOne(
            'SELECT s.*, st.admission_number, sf.staff_number,
                    CONCAT(u.first_name, " ", u.last_name) AS sold_by_name,
                    CONCAT(v.first_name, " ", v.last_name) AS voided_by_name
               FROM store_sales s
               LEFT JOIN students st ON st.id = s.student_id
               LEFT JOIN staff sf    ON sf.id = s.staff_id
               LEFT JOIN users u     ON u.id = s.sold_by
               LEFT JOIN users v     ON v.id = s.voided_by
              WHERE s.id = ?', [(int) $id]
        );
        if ($sale === null) {
            $this->abort(404, 'Sale not found.');
        }
        return $this->view('store.sales.show', [
            'pageTitle' => 'Receipt ' . $sale['receipt_number'],
            'sale'      => $sale,
            'items'     => Database::select('SELECT * FROM store_sale_items WHERE sale_id = ? ORDER BY id', [(int) $id]),
        ]);
    }

    /** Cancel a sale: stock goes back on the shelf and the sale drops out of the takings. */
    public function void(Request $request, string $id): never
    {
        $this->authorize('store.void');
        $this->verifyCsrf($request);
        $back = '/store/sales/' . (int) $id;
        $reason = mb_substr(trim((string) $request->input('reason', '')), 0, 255);
        if ($reason === '') {
            $this->error('Give a reason for voiding the sale.', $back);
        }
        $error = null;
        Database::transaction(function () use ($id, $reason, &$error): void {
            $sale = Database::selectOne('SELECT * FROM store_sales WHERE id = ? FOR UPDATE', [(int) $id]);
            if ($sale === null || $sale['status'] !== 'completed') {
                $error = 'Only a completed sale can be voided.';
                return;
            }
            $now = date('Y-m-d H:i:s');
            foreach (Database::select('SELECT * FROM store_sale_items WHERE sale_id = ? AND product_id IS NOT NULL', [(int) $id]) as $item) {
                $p = Database::selectOne('SELECT quantity FROM store_products WHERE id = ? FOR UPDATE', [(int) $item['product_id']]);
                if ($p === null) {
                    continue;
                }
                $balance = round((float) $p['quantity'] + (float) $item['quantity'], 2);
                Database::statement('UPDATE store_products SET quantity = ? WHERE id = ?', [$balance, (int) $item['product_id']]);
                Database::statement(
                    'INSERT INTO store_stock_movements (product_id, direction, quantity, balance_after, sale_id, reference, note, created_by, created_at)
                     VALUES (?, "void", ?, ?, ?, ?, ?, ?, ?)',
                    [(int) $item['product_id'], $item['quantity'], $balance, (int) $id, $sale['receipt_number'], $reason, Auth::id(), $now]
                );
            }
            Database::statement(
                'UPDATE store_sales SET status = "voided", void_reason = ?, voided_by = ?, voided_at = ? WHERE id = ?',
                [$reason, Auth::id(), $now, (int) $id]
            );
            Audit::updated('Store sale', (int) $id, ['status' => 'completed'], ['status' => 'voided', 'reason' => $reason], 'store');
        });
        if ($error !== null) {
            $this->error($error, $back);
        }
        $this->success('Sale voided and stock returned.', $back);
    }

    /** Bounce back to the till keeping the basket and customer the cashier entered. */
    private function fail(string $message, Request $request, string $back): never
    {
        Session::keepOldInput($request->all());
        $this->error($message, $back);
    }

    private function qty(mixed $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    }

    private function dateOr(string $value, string $default): string
    {
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value ? $value : $default;
    }
}
