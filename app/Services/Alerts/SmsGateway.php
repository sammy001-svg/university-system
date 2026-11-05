<?php
declare(strict_types=1);

namespace App\Services\Alerts;

use App\Core\Config;
use App\Core\Logger;
use App\Support\Http;
use RuntimeException;

/**
 * Sends one SMS through the gateway named by SMS_DRIVER in .env:
 *   log            write to storage/logs/sms.log (default; nothing is sent)
 *   africastalking Africa's Talking (AT_USERNAME, AT_API_KEY, SMS_SENDER)
 *   twilio         Twilio (TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM)
 *   http           any gateway with a simple GET/POST API: SMS_HTTP_URL with {to}, {message} and {sender} placeholders
 */
final class SmsGateway
{
    /** @throws RuntimeException when the gateway refuses the message */
    public static function send(string $to, string $message): void
    {
        $cfg = (array) Config::get('sms', []);
        $to  = self::normalise($to, (string) ($cfg['country_code'] ?? '231'));
        if ($to === null) {
            throw new RuntimeException('Not a valid mobile number.');
        }

        switch ($cfg['driver'] ?? 'log') {
            case 'africastalking':
                $res = Http::request('POST', 'https://api.africastalking.com/version1/messaging', [
                    'apiKey' => (string) $cfg['at_api_key'], 'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ], array_filter(['username' => $cfg['at_username'], 'to' => $to, 'message' => $message, 'from' => $cfg['sender'] ?: null]));
                $status = $res['json']['SMSMessageData']['Recipients'][0]['status'] ?? null;
                if ($res['status'] >= 300 || ($status !== null && !in_array($status, ['Success', 'Sent'], true))) {
                    throw new RuntimeException('Africa\'s Talking: ' . ($status ?? $res['error'] ?? 'HTTP ' . $res['status']));
                }
                return;
            case 'twilio':
                $res = Http::request('POST', 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode((string) $cfg['twilio_sid']) . '/Messages.json', [
                    'Authorization' => 'Basic ' . base64_encode($cfg['twilio_sid'] . ':' . $cfg['twilio_token']),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ], ['To' => $to, 'From' => $cfg['twilio_from'], 'Body' => $message]);
                if ($res['status'] >= 300) {
                    throw new RuntimeException('Twilio: ' . ($res['json']['message'] ?? $res['error'] ?? 'HTTP ' . $res['status']));
                }
                return;
            case 'http':
                $url = strtr((string) $cfg['http_url'], [
                    '{to}' => rawurlencode($to), '{message}' => rawurlencode($message), '{sender}' => rawurlencode((string) $cfg['sender']),
                ]);
                $res = Http::request(($cfg['http_method'] ?? 'GET') === 'POST' ? 'POST' : 'GET', $url);
                if ($res['error'] !== null || $res['status'] >= 300) {
                    throw new RuntimeException('SMS gateway: ' . ($res['error'] ?? 'HTTP ' . $res['status']));
                }
                return;
            default:
                Logger::write('info', "SMS to {$to}: {$message}");
                @file_put_contents(Config::get('paths.storage') . '/logs/sms.log',
                    '[' . date('Y-m-d H:i:s') . "] {$to}: {$message}\n", FILE_APPEND | LOCK_EX);
        }
    }

    /** "0770 123 456" -> "+231770123456"; null when it cannot be a phone number. */
    public static function normalise(string $phone, string $countryCode = '231'): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', $phone);
        if (str_starts_with(trim($phone), '+')) {
            $number = '+' . $digits;
        } elseif (str_starts_with($digits, '00')) {
            $number = '+' . substr($digits, 2);
        } elseif (str_starts_with($digits, $countryCode) && strlen($digits) > 9) {
            $number = '+' . $digits;
        } else {
            $number = '+' . $countryCode . ltrim($digits, '0');
        }
        return strlen($number) >= 10 && strlen($number) <= 16 ? $number : null;
    }
}
