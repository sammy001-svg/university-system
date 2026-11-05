<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Invoice;
use App\Services\Payments\OnlinePaymentService;
use RuntimeException;

/** Paying fees online, by the student or a linked parent/sponsor. */
final class PayController extends Controller
{
    public function form(Request $request, string $studentId, string $invoiceId): string
    {
        $student = $this->payable((int) $studentId);
        $invoice = $this->invoice((int) $studentId, (int) $invoiceId);
        $first   = Database::selectOne(
            'SELECT amount - amount_paid AS outstanding, label FROM invoice_installments WHERE invoice_id = ? AND installment_number = 1',
            [$invoice['id']]
        );
        return $this->view('pay.form', [
            'pageTitle' => 'Pay fees online',
            'student'   => $student,
            'invoice'   => $invoice,
            'firstDue'  => $first !== null && (float) $first['outstanding'] > 0.005 ? round((float) $first['outstanding'], 2) : null,
            'providers' => OnlinePaymentService::available(),
            'backUrl'   => $this->backUrl((int) $studentId),
            'pending'   => Database::select(
                'SELECT * FROM online_payments WHERE invoice_id = ? AND status IN ("pending","awaiting_verification") ORDER BY id DESC',
                [$invoice['id']]
            ),
        ]);
    }

    public function start(Request $request, string $studentId, string $invoiceId): never
    {
        $this->verifyCsrf($request);
        $this->payable((int) $studentId);
        $invoice = $this->invoice((int) $studentId, (int) $invoiceId);
        $back    = '/pay/' . $studentId . '/' . $invoiceId;
        try {
            $reference = OnlinePaymentService::start((int) $studentId, (int) $invoice['id'], (float) $request->input('amount'),
                (string) $request->input('provider'), $request->input('payer_phone') ?: null,
                $request->input('provider_reference') ?: null, $request->file('proof'), (int) Auth::id());
        } catch (RuntimeException $e) {
            \App\Core\Session::keepOldInput($request->all());
            $this->error($e->getMessage(), $back);
        }
        $this->redirect('/pay/status/' . $reference);
    }

    public function status(Request $request, string $reference): string
    {
        $op = $this->own($reference);
        $op = OnlinePaymentService::refresh($op);
        return $this->view('pay.status', [
            'pageTitle' => 'Payment status',
            'op'        => $op,
            'backUrl'   => $this->backUrl((int) $op['student_id']),
        ]);
    }

    /** Polled by the status page while a MoMo payment waits for the payer's approval. */
    public function check(Request $request, string $reference): never
    {
        $op = OnlinePaymentService::refresh($this->own($reference));
        Response::json(['status' => $op['status'], 'message' => $op['message'], 'receipt' => $op['receipt_number']]);
    }

    /* ---------------- Access ---------------- */

    /** The student, if the signed-in user is that student or one of their linked parents/sponsors. */
    private function payable(int $studentId): array
    {
        $student = Database::selectOne(
            'SELECT s.id, s.user_id, s.admission_number, u.first_name, u.last_name, u.phone FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?',
            [$studentId]
        );
        $allowed = $student !== null && ((int) $student['user_id'] === (int) Auth::id()
            || Database::scalar('SELECT 1 FROM guardians WHERE student_id = ? AND user_id = ?', [$studentId, Auth::id()]));
        if (!$allowed) {
            throw new HttpException(403, 'You can pay only for your own account or a student linked to you.');
        }
        return $student;
    }

    private function invoice(int $studentId, int $invoiceId): array
    {
        $invoice = (new Invoice())->find($invoiceId);
        if ($invoice === null || (int) $invoice['student_id'] !== $studentId || in_array($invoice['status'], ['cancelled', 'draft'], true)) {
            throw new HttpException(404, 'Invoice not found.');
        }
        return $invoice;
    }

    private function own(string $reference): array
    {
        $op = OnlinePaymentService::find($reference);
        if ($op === null) {
            throw new HttpException(404, 'Payment not found.');
        }
        $this->payable((int) $op['student_id']);
        return $op;
    }

    private function backUrl(int $studentId): string
    {
        return Auth::user()['user_type'] === 'student' ? '/portal/finance' : '/family/' . $studentId . '/fees';
    }
}
