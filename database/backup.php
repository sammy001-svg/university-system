<?php
/**
 * Back up the database to storage/backups (gzipped SQL), keeping the newest N
 * (Settings > Security > Number of Backups to Keep).
 *
 *   php database/backup.php
 *
 * The web application also makes one backup a day by itself; schedule this
 * command (e.g. nightly with cron or Windows Task Scheduler) for a backup that
 * does not depend on anyone opening a page:
 *   30 2 * * *  php /path/to/database/backup.php
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

$started = microtime(true);
$name    = App\Services\Security\BackupService::create();
$size    = filesize(App\Services\Security\BackupService::path($name));
printf("Backup %s written (%.2f MB in %.1f s).\n", $name, $size / 1048576, microtime(true) - $started);
