<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Hash;
use App\Core\Logger;
use App\Core\Setting;
use App\Core\Upload;
use App\Services\BillingService;
use RuntimeException;
use Throwable;

/**
 * Fees paid online by a student or their parent/sponsor.
 *
 * - mtn_momo: MTN MoMo request-to-pay through the API; posted automatically once MTN confirms.
 * - orange_money, mtn_manual, bank_transfer: the payer sends the money and enters the transaction
 *   ID (and may attach the SMS/receipt); the bursar checks it and approves.
 *
 * Every confirmed payment goes through BillingService::recordPayment, so receipts, invoice balances,
 * the 1st-installment enrolment rule and the alerts work exactly as for a cashier payment.
 */
final class OnlinePaymentService
{
    public const PROVIDERS = [
        'mtn_momo'      => 'MTN Mobile Money (approve on your phone)',
        'orange_money'  => 'Orange Money (send, then enter the transaction ID)',
        'mtn_manual'    => 'MTN Mobile Money (send, then enter the transaction ID)',
        'bank_transfer' => 'Bank transfer or deposit',
    ];

    /** Providers the institution has set up, with the instructions payers see. */
    public static function available(): array
    {
        if (!(bool) Setting::get('online_payments_enabled', true)) {
            return [];
        }
        $out = [];
        if (MomoClient::enabled()) {
            $out['mtn_momo'] = 'You will receive a prompt on your MTN phone. Enter your MoMo PIN to approve the payment.';
        } elseif (trim((string) Setting::get('mtn_momo_merchant_number', '')) !== '') {
            $out['mtn_manual'] = 'Send the amount by MTN MoMo to ' . Setting::get('mtn_momo_merchant_number') . ', then enter the transaction ID from the confirmation SMS.';
        }
        if (trim((string) Setting::get('orange_money_merchant_number', '')) !== '') {
            $out['orange_money'] = 'Send the amount by Orange Money to ' . Setting::get('orange_money_merchant_number') . ', then enter the transaction ID from the confirmation SMS.';
        }
        if (trim((string) Setting::get('bank_payment_details', '')) !== '') {
            $out['bank_transfer'] = "Pay into:\n" . Setting::get('bank_payment_details') . "\nThen enter the bank's transaction or deposit slip number.";
        }
        return $out;
    }

    public static function find(string $reference): ?array
    {
        return Database::selectOne(
            'SELECT op.*, i.invoice_number, s.admission_number, s.user_id AS student_user_id, u.first_name, u.last_name, p.receipt_number
               FROM online_payments op
               JOIN students s ON s.id = op.student_id
               JOIN users u    ON u.id = s.user_id
          LEFT JOIN invoices i ON i.id = op.invoice_id
          LEFT JOIN payments p ON p.id = op.payment_id
              WHERE op.reference = ?',
            [$reference]
        );
    }

    /** Start a payment; returns its reference. */
    public static function start(int $studentId, int $invoiceId, float $amount, string $provider, ?string $phone,
                                 ?string $providerRef, ?array $proof, int $initiatedBy): string
    {
        if (!isset(self::available()[$provider])) {
            throw new RuntimeException('That payment method is not available.');
        }
        $invoice = Database::selectOne('SELECT * FROM invoices WHERE id = ? AND student_id = ? AND status NOT IN ("cancelled","draft")', [$invoiceId, $studentId]);
        if ($invoice === null) {
            throw new RuntimeException('Invoice not found.');
        }
        $amount = round($amount, 2);
        if ($amount < 1) {
            throw new RuntimeException('Enter an amount of at least ' . money(1) . '.');
        }
        if ($amount > (float) $invoice['balance'] + 0.005) {
            throw new RuntimeException('The amount is more than the ' . money($invoice['balance']) . ' outstanding on this invoice.');
        }
        $providerRef = $providerRef !== null ? strtoupper(trim($providerRef)) : null;
        if ($provider !== 'mtn_momo') {
            if ($providerRef === null || strlen($providerRef) < 4) {
                throw new RuntimeException('Enter the transaction ID from your payment confirmation.');
            }
            if (Database::scalar('SELECT 1 FROM online_payments WHERE provider = ? AND provider_reference = ? AND status <> "rejected"', [$provider, $providerRef])) {
                throw new RuntimeException('That transaction ID has already been submitted.');
            }
        } elseif ($phone === null || strlen((string) preg_replace('/\D/', '', $phone)) < 7) {
            throw new RuntimeException('Enter the MTN number that will pay.');
        }

        $proofPath = null;
        if ($proof !== null) {
            $proofPath = Upload::storePrivate($proof, 'payment-proofs', array_merge(Upload::IMAGES, ['pdf']), 8)['path'];
        }
        $reference = Hash::uuid4();
        $now       = date('Y-m-d H:i:s');
        Database::statement(
            'INSERT INTO online_payments (reference, student_id, invoice_id, initiated_by, provider, payer_phone, amount, currency, status,
                                          provider_reference, proof_path, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$reference, $studentId, $invoiceId, $initiatedBy, $provider, $phone, $amount, (string) Setting::get('currency_code', 'USD'),
             $provider === 'mtn_momo' ? 'pending' : 'awaiting_verification', $provider === 'mtn_momo' ? null : $providerRef, $proofPath, $now, $now]
        );

        if ($provider === 'mtn_momo') {
            try {
                (new MomoClient())->requestToPay($reference, $amount, (string) $phone, $invoice['invoice_number'],
                    'Fees ' . $invoice['invoice_number']);
            } catch (Throwable $e) {
                self::setStatus($reference, 'failed', $e->getMessage());
                Logger::error('MoMo request-to-pay failed: ' . $e->getMessage());
                throw new RuntimeException('The payment could not be started: ' . $e->getMessage());
            }
        }
        return $reference;
    }

    /** Ask MTN for the outcome of a pending MoMo payment and post it when successful. */
    public static function refresh(array $op): array
    {
        if ($op['provider'] !== 'mtn_momo' || $op['status'] !== 'pending' || !MomoClient::enabled()) {
            return $op;
        }
        try {
            $result = (new MomoClient())->status((string) $op['reference']);
        } catch (Throwable $e) {
            Logger::error('MoMo status check failed: ' . $e->getMessage());
            return $op;
        }
        if ($result['status'] === 'SUCCESSFUL') {
            Database::statement('UPDATE online_payments SET provider_reference = ? WHERE id = ?', [$result['financialTransactionId'], $op['id']]);
            self::complete((string) $op['reference'], null);
        } elseif ($result['status'] === 'FAILED' || $result['status'] === 'REJECTED' || $result['status'] === 'TIMEOUT') {
            self::setStatus((string) $op['reference'], 'failed', $result['reason'] ?: 'The payment was declined or not approved in time.');
        } elseif (time() - strtotime((string) $op['created_at']) > 3600) {
            self::setStatus((string) $op['reference'], 'failed', 'Not approved within an hour.');
        }
        return self::find((string) $op['reference']);
    }

    /** Post the money to the student's account (once). */
    public static function complete(string $reference, ?int $verifiedBy): array
    {
        $result = Database::transaction(static function () use ($reference, $verifiedBy): array {
            $op = Database::selectOne('SELECT * FROM online_payments WHERE reference = ? FOR UPDATE', [$reference]);
            if ($op === null) {
                throw new RuntimeException('Payment not found.');
            }
            if ($op['payment_id'] !== null) {
                return ['ok' => true, 'message' => 'Already posted.'];
            }
            if (!in_array($op['status'], ['pending', 'awaiting_verification'], true)) {
                throw new RuntimeException('Only a pending payment can be confirmed.');
            }
            $label  = ['mtn_momo' => 'MTN MoMo', 'mtn_manual' => 'MTN MoMo', 'orange_money' => 'Orange Money', 'bank_transfer' => 'Bank'][$op['provider']];
            $posted = (new BillingService())->recordPayment([
                'student_id' => $op['student_id'],
                'invoice_id' => $op['invoice_id'],
                'amount'     => $op['amount'],
                'method'     => $op['provider'] === 'bank_transfer' ? 'bank_transfer' : 'mobile_money',
                'reference'  => mb_substr($label . ' ' . ($op['provider_reference'] ?? $op['reference']), 0, 100),
                'notes'      => 'Online payment ' . $op['reference'] . ($op['payer_phone'] ? ' from ' . $op['payer_phone'] : ''),
            ], $verifiedBy);
            if (!$posted['ok']) {
                throw new RuntimeException($posted['message']);
            }
            $paymentId = (int) Database::scalar('SELECT id FROM payments WHERE receipt_number = ?', [$posted['receipt']]);
            Database::statement(
                'UPDATE online_payments SET status = "successful", payment_id = ?, verified_by = ?, verified_at = ?, message = NULL, updated_at = ? WHERE id = ?',
                [$paymentId, $verifiedBy, $verifiedBy ? date('Y-m-d H:i:s') : null, date('Y-m-d H:i:s'), $op['id']]
            );
            return ['ok' => true, 'message' => $posted['message']];
        });
        Audit::log('online_payment', 'finance', 'online_payments', $reference, 'Online payment posted: ' . $result['message']);
        return $result;
    }

    public static function reject(string $reference, string $reason, int $userId): void
    {
        $op = self::find($reference);
        if ($op === null || $op['status'] !== 'awaiting_verification') {
            throw new RuntimeException('Only a payment awaiting verification can be rejected.');
        }
        Database::statement(
            // The transaction ID is released (suffixed) so a corrected submission can use it again.
            'UPDATE online_payments SET status = "rejected", provider_reference = CONCAT(LEFT(provider_reference, 80), "-REJ", id),
                    message = ?, verified_by = ?, verified_at = ?, updated_at = ? WHERE id = ?',
            [mb_substr($reason, 0, 255), $userId, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $op['id']]
        );
        \App\Services\Alerts\Alerts::send((int) $op['student_user_id'], 'payment_received', 'Payment not confirmed',
            'Your ' . money($op['amount']) . ' payment (ref ' . $op['provider_reference'] . ') could not be confirmed: ' . $reason,
            '/portal/finance', 'danger', 'money');
        Audit::log('online_payment_rejected', 'finance', 'online_payments', $reference, 'Rejected: ' . $reason);
    }

    private static function setStatus(string $reference, string $status, ?string $message): void
    {
        Database::statement('UPDATE online_payments SET status = ?, message = ?, updated_at = ? WHERE reference = ?',
            [$status, $message !== null ? mb_substr($message, 0, 255) : null, date('Y-m-d H:i:s'), $reference]);
    }
}
