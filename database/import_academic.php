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

use App\Core\Config;

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

/* ---------------- 1. Schema upgrade (idempotent) ---------------- */
$hasColumn = static fn (string $table, string $column): bool => (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = '{$column}'"
)->fetchColumn() > 0;
$hasIndex = static fn (string $table, string $index): bool => (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = '{$table}' AND index_name = '{$index}'"
)->fetchColumn() > 0;
$hasForeignKey = static fn (string $table, string $name): bool => (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.table_constraints
      WHERE table_schema = DATABASE() AND table_name = '{$table}' AND constraint_name = '{$name}' AND constraint_type = 'FOREIGN KEY'"
)->fetchColumn() > 0;

echo "Checking schema...\n";
$pdo->exec("ALTER TABLE programs MODIFY `level`
            ENUM('certificate','diploma','associate','bachelor','postgraduate_diploma','masters','phd') NOT NULL DEFAULT 'bachelor'");

if (!$hasColumn('program_courses', 'credit_hours')) {
    echo "  Upgrading program_courses (slots, per-programme credits, either/or choices)...\n";
    if ($hasForeignKey('program_courses', 'fk_pc_course')) {
        $pdo->exec('ALTER TABLE program_courses DROP FOREIGN KEY fk_pc_course');
    }
    if (!$hasIndex('program_courses', 'ix_pc_plan')) {
        $pdo->exec('ALTER TABLE program_courses ADD KEY ix_pc_plan (program_id, year_of_study, semester_number)');
    }
    if ($hasIndex('program_courses', 'uq_program_course')) {
        $pdo->exec('ALTER TABLE program_courses DROP INDEX uq_program_course');
    }
    $pdo->exec(
        "ALTER TABLE program_courses
           MODIFY course_id INT UNSIGNED DEFAULT NULL,
           ADD COLUMN slot_label VARCHAR(120) DEFAULT NULL AFTER course_id,
           ADD COLUMN credit_hours TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER semester_number,
           MODIFY course_type ENUM('core','elective','common','audit','minor','concentration') NOT NULL DEFAULT 'core',
           ADD COLUMN choice_group TINYINT UNSIGNED DEFAULT NULL AFTER course_type,
           ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER choice_group,
           ADD COLUMN note VARCHAR(255) DEFAULT NULL AFTER sort_order,
           ADD UNIQUE KEY uq_program_course (program_id, year_of_study, semester_number, course_id),
           ADD CONSTRAINT fk_pc_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE"
    );
    // Existing rows keep the course's own credit hours.
    $pdo->exec('UPDATE program_courses pc JOIN courses c ON c.id = pc.course_id SET pc.credit_hours = c.credit_hours');
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS program_course_options (
       id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
       program_id   INT UNSIGNED NOT NULL,
       list_type    ENUM('elective','minor','concentration') NOT NULL,
       group_label  VARCHAR(120)          DEFAULT NULL,
       course_id    INT UNSIGNED          DEFAULT NULL,
       slot_label   VARCHAR(120)          DEFAULT NULL,
       credit_hours TINYINT UNSIGNED NOT NULL DEFAULT 3,
       choice_group TINYINT UNSIGNED      DEFAULT NULL,
       sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
       note         VARCHAR(255)          DEFAULT NULL,
       PRIMARY KEY (id),
       KEY ix_pco_program (program_id, list_type, sort_order),
       CONSTRAINT fk_pco_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE CASCADE,
       CONSTRAINT fk_pco_course  FOREIGN KEY (course_id)  REFERENCES courses (id)  ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "  Schema up to date.\n";

/* ---------------- 2. Load the catalogue ---------------- */
echo "Loading catalogue...\n";
$academic = require $base . '/database/seeds/academic.php';
$pdo->beginTransaction();
try {
    $stats = $academic($pdo, ['set_current' => isset($options['set-current'])]);

    if (isset($options['deactivate-others'])) {
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
        echo "  Marked inactive (not in the catalogue): " . http_build_query($retired, '', ', ') . "\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Import failed, nothing was changed:\n" . $e->getMessage() . "\n");
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
