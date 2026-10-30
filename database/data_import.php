<?php
/**
 * Import the data files in database/data/ (students, lecturers, staff).
 *
 * The web application does this by itself on the first page load after a
 * file is added or changed (see App\Services\DataImport\DataImportSync); this
 * command is for doing it on demand.
 *
 *   php database/data_import.php                 import every new or changed file
 *   php database/data_import.php --all           re-import every file
 *   php database/data_import.php <file>          import one file now
 *   php database/data_import.php <file> --dry-run   check one file, save nothing
 *
 * New accounts have no password yet: issue first-time passwords from
 * Admin > Data Imports > Login slips.
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

use App\Core\Database;
use App\Services\AcademicCatalogSync;
use App\Services\DataImport\DataImportSync;

$args   = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => !str_starts_with($a, '--')));
$dryRun = in_array('--dry-run', $argv, true);
if ($args !== [] && !is_file($args[0])) {
    exit("No such file: {$args[0]}\nUsage: php database/data_import.php [<data file> [--dry-run]] [--all]\n");
}

// The schema and the catalogue the data refers to must be current first.
(new AcademicCatalogSync($base))->load(Database::connection(), false);
$sync = new DataImportSync($base);
DataImportSync::ensureTable();

if ($args !== []) {
    $file    = (string) realpath($args[0]);
    $reports = [basename($file) => $sync->importFile($file, null, null, $dryRun)];
} else {
    $reports = $sync->importPending(in_array('--all', $argv, true));
}

if ($reports === []) {
    echo "Every data file is already imported.\n";
}
$failed = 0;
foreach ($reports as $name => $report) {
    echo $name . ($dryRun ? ' (dry run: nothing saved)' : '') . "\n" . str_repeat('=', 72) . "\n" . $report->text() . "\n\n";
    $failed += $report->failed + (str_contains($report->text(), 'STOPPED:') ? 1 : 0);
}
exit($failed > 0 ? 1 : 0);
