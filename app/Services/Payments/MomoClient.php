<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Config;
use App\Support\Http;
use RuntimeException;

/**
 * MTN Mobile Money Collection API (https://momodeveloper.mtn.com): the payer
 * gets a prompt on their phone, approves it with their PIN, and we poll the
 * transaction until it succeeds or fails. Credentials come from .env (MOMO_*).
 */
final class MomoClient
{
    private array $cfg;

    public function __construct()
    {
        $this->cfg = (array) Config::get('momo', []);
    }

    public static function enabled(): bool
    {
        $cfg = (array) Config::get('momo', []);
        return !empty($cfg['enabled']) && $cfg['subscription_key'] !== '' && $cfg['api_user'] !== '' && $cfg['api_key'] !== '';
    }

    /** Ask the payer's phone to approve the payment. $reference is our UUID for it. */
    public function requestToPay(string $reference, float $amount, string $phone, string $externalId, string $note): void
    {
        $res = Http::request('POST', $this->cfg['base_url'] . '/collection/v1_0/requesttopay', $this->headers() + [
            'X-Reference-Id' => $reference,
            'Content-Type'   => 'application/json',
        ], json_encode([
            'amount'       => number_format($amount, 2, '.', ''),
            'currency'     => $this->cfg['currency'],
            'externalId'   => $externalId,
            'payer'        => ['partyIdType' => 'MSISDN', 'partyId' => $this->msisdn($phone)],
            'payerMessage' => mb_substr($note, 0, 160),
            'payeeNote'    => mb_substr($note, 0, 160),
        ]), 30);
        if ($res['status'] !== 202) {
            throw new RuntimeException('MTN MoMo did not accept the request: ' . $this->reason($res));
        }
    }

    /** @return array{status:string, reason:?string, financialTransactionId:?string} status PENDING|SUCCESSFUL|FAILED */
    public function status(string $reference): array
    {
        $res = Http::request('GET', $this->cfg['base_url'] . '/collection/v1_0/requesttopay/' . rawurlencode($reference), $this->headers(), null, 20);
        if ($res['status'] !== 200 || !is_array($res['json'])) {
            throw new RuntimeException('Could not check the MTN MoMo payment: ' . $this->reason($res));
        }
        $reason = $res['json']['reason'] ?? null;
        return [
            'status'                 => strtoupper((string) ($res['json']['status'] ?? 'PENDING')),
            'reason'                 => is_array($reason) ? ($reason['message'] ?? $reason['code'] ?? null) : $reason,
            'financialTransactionId' => $res['json']['financialTransactionId'] ?? null,
        ];
    }

    private function headers(): array
    {
        return [
            'Authorization'             => 'Bearer ' . $this->token(),
            'X-Target-Environment'      => (string) $this->cfg['target_env'],
            'Ocp-Apim-Subscription-Key' => (string) $this->cfg['subscription_key'],
        ];
    }

    /** OAuth token, cached until shortly before it expires. */
    private function token(): string
    {
        $cache = Config::get('paths.storage') . '/cache/momo_token.json';
        $saved = is_file($cache) ? json_decode((string) file_get_contents($cache), true) : null;
        if (is_array($saved) && ($saved['expires'] ?? 0) > time() + 60 && ($saved['user'] ?? '') === $this->cfg['api_user']) {
            return (string) $saved['token'];
        }
        $res = Http::request('POST', $this->cfg['base_url'] . '/collection/token/', [
            'Authorization'             => 'Basic ' . base64_encode($this->cfg['api_user'] . ':' . $this->cfg['api_key']),
            'Ocp-Apim-Subscription-Key' => (string) $this->cfg['subscription_key'],
            'Content-Length'            => '0',
        ], '', 20);
        $token = $res['json']['access_token'] ?? null;
        if ($res['status'] !== 200 || !is_string($token)) {
            throw new RuntimeException('Could not sign in to MTN MoMo: ' . $this->reason($res));
        }
        @file_put_contents($cache, json_encode([
            'token' => $token, 'user' => $this->cfg['api_user'], 'expires' => time() + (int) ($res['json']['expires_in'] ?? 3600),
        ]), LOCK_EX);
        return $token;
    }

    /** "0881 234 567" -> "231881234567" (international format without the plus). */
    private function msisdn(string $phone): string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        $cc     = (string) $this->cfg['country_code'];
        return str_starts_with($digits, $cc) && strlen($digits) > 9 ? $digits : $cc . ltrim($digits, '0');
    }

    private function reason(array $res): string
    {
        return (string) ($res['error'] ?? $res['json']['message'] ?? $res['json']['reason'] ?? ('HTTP ' . $res['status']));
    }
}
