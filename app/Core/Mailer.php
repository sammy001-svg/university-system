<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal mail sender. When MAIL_ENABLED=false messages are written to
 * storage/logs/mail.log so the workflows can be exercised without an SMTP server.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, array $options = []): bool
    {
        $cfg      = Config::get('mail');
        $fromName = $options['from_name'] ?? $cfg['from_name'];
        $from     = $options['from'] ?? $cfg['from'];

        if (!$cfg['enabled']) {
            self::logToFile($to, $subject, $htmlBody);
            return true;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::encodeName($fromName) . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: UMS',
        ];

        $sent = @mail($to, self::encodeName($subject), self::wrap($subject, $htmlBody), implode("\r\n", $headers));
        if (!$sent) {
            Logger::error('Mail delivery failed', ['to' => $to, 'subject' => $subject]);
            self::logToFile($to, $subject, $htmlBody);
        }
        return $sent;
    }

    private static function encodeName(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function logToFile(string $to, string $subject, string $body): void
    {
        $dir  = Config::get('paths.storage') . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "==== %s ====\nTo: %s\nSubject: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            strip_tags($body)
        );
        @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }

    /** Wrap a message body in the institutional email shell. */
    public static function wrap(string $title, string $body): string
    {
        $appName = htmlspecialchars((string) Setting::get('institution_name', Config::get('app.name')), ENT_QUOTES);
        $year    = date('Y');
        return <<<HTML
<!doctype html>
<html><body style="margin:0;padding:24px;background:#f1f5f9;font-family:Segoe UI,Arial,sans-serif;color:#0f172a">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="background:#0f2c52;color:#fff;padding:20px 24px">
      <h1 style="margin:0;font-size:18px;letter-spacing:.3px">{$appName}</h1>
    </div>
    <div style="padding:24px;line-height:1.6;font-size:14px">
      <h2 style="margin:0 0 16px;font-size:16px;color:#0f2c52">{$title}</h2>
      {$body}
    </div>
    <div style="padding:16px 24px;background:#f8fafc;color:#64748b;font-size:12px;border-top:1px solid #e2e8f0">
      This is an automated message from the {$appName} management system &middot; &copy; {$year}
    </div>
  </div>
</body></html>
HTML;
    }
}
