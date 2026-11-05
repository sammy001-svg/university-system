<?php
declare(strict_types=1);

namespace App\Services\Security;

use App\Core\Database;

/**
 * Two-step sign-in with an authenticator app (Google Authenticator, Microsoft
 * Authenticator, Authy ...): time-based one-time passwords, RFC 6238
 * (HMAC-SHA1, 30-second steps, 6 digits), plus single-use recovery codes.
 */
final class TwoFactor
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        $secret = '';
        foreach (str_split(random_bytes(20)) as $byte) {
            $secret .= self::BASE32[ord($byte) & 31];
        }
        return $secret;
    }

    /** otpauth:// link the authenticator app reads from the QR code. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    /** Accepts the current code and the ones either side of it (clock drift). */
    public static function verify(string $secret, string $code): bool
    {
        $code = (string) preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return false;
        }
        $step = intdiv(time(), 30);
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals(self::code($secret, $step + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function code(string $secret, int $step): string
    {
        $key  = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0) . pack('N*', $step), $key, true);
        $off  = ord($hash[19]) & 0x0f;
        $bin  = ((ord($hash[$off]) & 0x7f) << 24) | (ord($hash[$off + 1]) << 16) | (ord($hash[$off + 2]) << 8) | ord($hash[$off + 3]);
        return str_pad((string) ($bin % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** @return list<string> ten new recovery codes (shown once); their hashes are stored. */
    public static function newRecoveryCodes(int $userId): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(3)) . '-' . bin2hex(random_bytes(3)));
        }
        Database::statement('UPDATE users SET two_factor_recovery_codes = ? WHERE id = ?',
            [json_encode(array_map(static fn (string $c): string => password_hash($c, PASSWORD_BCRYPT, ['cost' => 10]), $codes)), $userId]);
        return $codes;
    }

    /** Use up a recovery code; true when it was valid. */
    public static function useRecoveryCode(array $user, string $code): bool
    {
        $code   = strtoupper(trim($code));
        $hashes = json_decode((string) ($user['two_factor_recovery_codes'] ?? ''), true) ?: [];
        foreach ($hashes as $i => $hash) {
            if (password_verify($code, $hash)) {
                unset($hashes[$i]);
                Database::statement('UPDATE users SET two_factor_recovery_codes = ? WHERE id = ?', [json_encode(array_values($hashes)), $user['id']]);
                return true;
            }
        }
        return false;
    }

    public static function remainingRecoveryCodes(array $user): int
    {
        return count(json_decode((string) ($user['two_factor_recovery_codes'] ?? ''), true) ?: []);
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper((string) preg_replace('/[^A-Za-z2-7]/', '', $secret));
        $bits = '';
        foreach (str_split($secret) as $char) {
            $bits .= str_pad(decbin((int) strpos(self::BASE32, $char)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }
        return $out;
    }
}
