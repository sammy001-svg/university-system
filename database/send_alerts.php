<?php
/**
 * Send queued email and SMS alerts (Admin > Email & SMS Alerts).
 *
 *   php database/send_alerts.php
 *
 * Alerts are normally sent straight after the page that created them; run
 * this every few minutes from cron to deliver anything left over (e.g. when a
 * gateway was briefly down):  *\/5 * * * *  php /path/to/database/send_alerts.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}
$base = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($base): void {
    if (str_starts_with($class, 'App\\')) {
        $path = $base . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
App\Core\Config::load(require $base . '/config/config.php');
require_once $base . '/app/Support/helpers.php';

$sent = App\Services\Alerts\Alerts::dispatch(500);
echo "{$sent} alert(s) sent.\n";
