<?php
/**
 * Load the academic catalogue into an existing installation.
 *
 *   php database/import_academic.php                    upgrade schema, load/refresh the catalogue
 *   php database/import_academic.php --set-current      also make the semester in session today current
 *   php database/import_academic.php --deactivate-others  also mark colleges, departments, programmes and
 *                                                       courses that are not in the catalogue inactive
 *   php database/import_academic.php --report           list every note recorded against the source document
 *
 * The web application also does this by itself on the first page load after
 * the catalogue file changes (see App\Services\AcademicCatalogSync); this
 * command is for doing it on demand, with the options above.
 *
 * Safe to run repeatedly. Records are matched by code and updated in place;
 * each catalogue programme's curriculum is replaced with the catalogue's.
 * Nothing is deleted except those curriculum rows and grade bands that the
 * catalogue's grading scale no longer has.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

$base = dirname(__DIR__);
require_once $base . '/app/Core/Config.php';
require_once $base . '/app/Core/Database.php';
require_once $base . '/app/Core/Logger.php';
require_once $base . '/app/Services/AcademicCatalogSync.php';

use App\Core\Config;
use App\Services\AcademicCatalogSync;

Config::load(require $base . '/config/config.php');
$options = getopt('', ['set-current', 'deactivate-others', 'report', 'help']);
if (isset($options['help'])) {
    echo "Usage: php database/import_academic.php [--set-current] [--deactivate-others] [--report]\n";
    exit(0);
}

$cfg = Config::get('database');
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']),
    $cfg['user'],
    $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
echo "Academic catalogue import - database {$cfg['name']}\n" . str_repeat('=', 50) . "\n";

/* ---------------- Schema upgrade + catalogue load (one shared code path with the web auto-sync) ---------------- */
echo "Upgrading schema if needed and loading catalogue...
";
$sync = new AcademicCatalogSync($base);
try {
    $stats = $sync->load($pdo, isset($options['set-current']), isset($options['deactivate-others'])
        ? static function (PDO $pdo) use ($base): array {
            $catalog = require $base . '/database/seeds/catalog/bbiu.php';
            $quote   = static fn (array $codes): string => implode(',', array_map([$pdo, 'quote'], $codes));
            $retired = [
                'faculties'   => $pdo->exec('UPDATE faculties SET status = "inactive" WHERE code NOT IN (' . $quote(array_keys($catalog['colleges'])) . ')'),
                'departments' => $pdo->exec('UPDATE departments SET status = "inactive" WHERE code NOT IN (' . $quote(array_keys($catalog['departments'])) . ')'),
                'programs'    => $pdo->exec('UPDATE programs SET status = "inactive" WHERE code NOT IN (' . $quote(array_keys($catalog['programs'])) . ')'),
                'courses'     => $pdo->exec(
                    'UPDATE courses SET status = "inactive"
                      WHERE id NOT IN (SELECT pc.course_id FROM program_courses pc JOIN programs p ON p.id = pc.program_id
                                        WHERE pc.course_id IS NOT NULL AND p.status = "active")
                        AND id NOT IN (SELECT o.course_id FROM program_course_options o JOIN programs p ON p.id = o.program_id
                                        WHERE o.course_id IS NOT NULL AND p.status = "active")'
                ),
            ];
            echo "  Marked inactive (not in the catalogue): " . http_build_query($retired, '', ', ') . "
";
            return [];
        }
        : null);
} catch (Throwable $e) {
    fwrite(STDERR, "Import failed, no catalogue data was changed:
" . $e->getMessage() . "
");
    exit(1);
}

echo "  {$stats['colleges']} colleges, {$stats['departments']} departments, {$stats['programs']} programmes, {$stats['courses']} courses.\n";
echo "  {$stats['plan_rows']} curriculum rows, {$stats['option_rows']} elective/minor/concentration rows.\n";
echo "  Grading scale and pass mark applied; {$stats['years']} academic years, {$stats['semesters']} semesters.\n";
echo "  {$stats['notes']} rows carry a note about the source document (see --report).\n";
if (!isset($options['set-current'])) {
    echo "  The current semester was not changed (use --set-current to switch it).\n";
}

if (isset($options['report'])) {
    echo "\nSource-document notes\n" . str_repeat('-', 50) . "\n";
    $rows = $pdo->query(
        'SELECT p.code AS program, CONCAT("Y", pc.year_of_study, " S", pc.semester_number) AS term,
                COALESCE(c.code, pc.slot_label) AS item, pc.note
           FROM program_courses pc JOIN programs p ON p.id = pc.program_id LEFT JOIN courses c ON c.id = pc.course_id
          WHERE pc.note IS NOT NULL
         UNION ALL
         SELECT p.code, o.list_type, COALESCE(c.code, o.slot_label), o.note
           FROM program_course_options o JOIN programs p ON p.id = o.program_id LEFT JOIN courses c ON c.id = o.course_id
          WHERE o.note IS NOT NULL
          ORDER BY 1, 2'
    )->fetchAll();
    foreach ($rows as $row) {
        printf("%-7s %-13s %-11s %s\n", $row['program'], $row['term'], $row['item'], $row['note']);
    }
}
echo "\nDone.\n";
