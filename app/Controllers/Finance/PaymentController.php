<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Payment;

final class PaymentController extends Controller
{
    private Payment $model;
    public function __construct() { $this->model = new Payment(); }

    public function index(Request $request): string
    {
        $this->authorize('finance.view');
        $payments = Database::select(
            'SELECT p.*, s.admission_number, u.first_name, u.last_name
               FROM payments p
               JOIN students s ON s.id = p.student_id
               JOIN users u    ON u.id = s.user_id
              ORDER BY p.paid_at DESC
              LIMIT 200'
        );
        return $this->view('finance.payments.index', ['pageTitle' => 'Payments', 'payments' => $payments]);
    }

    public function create(Request $request): string
    {
        $this->authorize('finance.create');
        return $this->view('finance.payments.create', [
            'pageTitle' => 'Record Payment',
            'students'  => Database::select("SELECT s.id, CONCAT(s.admission_number,' – ',u.first_name,' ',u.last_name) AS label FROM students s JOIN users u ON u.id=s.user_id WHERE s.status='active' ORDER BY u.last_name"),
            'invoices'  => [],
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('finance.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'student_id'     => 'required|integer|exists:students,id',
            'invoice_id'     => 'nullable|integer|exists:invoices,id',
            'amount'         => 'required|numeric',
            'method'         => 'required|in:cash,mpesa,bank_transfer,cheque,card,other',
            'reference'      => 'nullable|max:80',
            'paid_at'        => 'required|date',
        ]);
        $data['received_by']    = Auth::id();
        $data['status']         = 'confirmed';
        $data['receipt_number'] = 'RCP-' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $id = $this->model->create($data);
        // Update invoice balance
        if (!empty($data['invoice_id'])) {
            Database::statement('UPDATE invoices SET amount_paid = amount_paid + ?, balance = balance - ?, updated_at=NOW() WHERE id=?',
                [$data['amount'], $data['amount'], $data['invoice_id']]);
        }
        $this->success('Payment recorded. Receipt: ' . $data['receipt_number'], '/finance/payments/' . $id . '/receipt');
    }

    public function receipt(Request $request, string $id): string
    {
        $this->authorize('finance.view');
        $payment = Database::selectOne(
            'SELECT p.*, s.admission_number, u.first_name, u.last_name, u.email
               FROM payments p JOIN students s ON s.id=p.student_id JOIN users u ON u.id=s.user_id WHERE p.id=?',
            [(int)$id]
        );
        if (!$payment) { throw new HttpException(404); }
        return $this->view('finance.payments.receipt', ['pageTitle' => 'Receipt', 'payment' => $payment]);
    }

    public function reverse(Request $request, string $id): never
    {
        $this->authorize('finance.reverse');
        $this->verifyCsrf($request);
        Database::statement("UPDATE payments SET status='reversed', updated_at=NOW() WHERE id=?", [(int)$id]);
        $this->success('Payment reversed.', '/finance/payments');
    }
}
