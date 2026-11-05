<?php
declare(strict_types=1);

namespace App\Controllers\Finance;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Payments\MomoClient;
use App\Services\Payments\OnlinePaymentService;
use App\Support\PrivateFile;
use RuntimeException;

/** The bursar's queue of online payments: verify mobile money / bank references, follow up MoMo. */
final class OnlinePaymentController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('finance.view');
        $status = (string) $request->query('status', 'awaiting_verification');

        // Bring pending MoMo payments up to date before listing them.
        foreach (Database::select('SELECT reference FROM online_payments WHERE status = "pending" AND provider = "mtn_momo" LIMIT 20') as $row) {
            OnlinePaymentService::refresh(OnlinePaymentService::find($row['reference']));
        }

        $sql    = 'SELECT op.*, s.admission_number, u.first_name, u.last_name, i.invoice_number, p.receipt_number,
                          CONCAT(iu.first_name, " ", iu.last_name) AS initiated_name, iu.user_type AS initiated_type
                     FROM online_payments op
                     JOIN students s ON s.id = op.student_id
                     JOIN users u    ON u.id = s.user_id
                LEFT JOIN invoices i ON i.id = op.invoice_id
                LEFT JOIN payments p ON p.id = op.payment_id
                LEFT JOIN users iu   ON iu.id = op.initiated_by';
        $params = [];
        if ($status !== 'all') {
            $sql     .= ' WHERE op.status = ?';
            $params[] = $status;
        }
        if ($request->filled('q')) {
            $sql     .= ($params ? ' AND' : ' WHERE') . ' (s.admission_number LIKE ? OR u.last_name LIKE ? OR op.provider_reference LIKE ? OR op.payer_phone LIKE ?)';
            $params   = array_merge($params, array_fill(0, 4, '%' . $request->query('q') . '%'));
        }
        return $this->view('finance.online-payments', [
            'pageTitle' => 'Online Payments',
            'rows'      => Database::select($sql . ' ORDER BY op.id DESC LIMIT 300', $params),
            'counts'    => array_column(Database::select('SELECT status, COUNT(*) AS n FROM online_payments GROUP BY status'), 'n', 'status'),
            'status'    => $status,
            'momo'      => MomoClient::enabled(),
            'providers' => OnlinePaymentService::available(),
        ]);
    }

    public function approve(Request $request, string $reference): never
    {
        $this->authorize('finance.create');
        $this->verifyCsrf($request);
        try {
            $result = OnlinePaymentService::complete($reference, (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/finance/online-payments');
        }
        $this->success('Payment confirmed and posted. ' . $result['message'], '/finance/online-payments');
    }

    public function reject(Request $request, string $reference): never
    {
        $this->authorize('finance.create');
        $this->verifyCsrf($request);
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            $this->error('Give the reason, so the payer knows what to do.', '/finance/online-payments');
        }
        try {
            OnlinePaymentService::reject($reference, $reason, (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/finance/online-payments');
        }
        $this->success('Payment rejected; the payer has been told why.', '/finance/online-payments');
    }

    public function proof(Request $request, string $reference): never
    {
        $this->authorize('finance.view');
        $op = OnlinePaymentService::find($reference);
        if ($op === null || !$op['proof_path']) {
            throw new HttpException(404);
        }
        PrivateFile::send($op['proof_path'], 'payment-proof-' . $op['admission_number'] . '.' . pathinfo((string) $op['proof_path'], PATHINFO_EXTENSION));
    }
}
