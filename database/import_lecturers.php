<?php
/**
 * Import lecturers from a data file (see storage/imports/lecturers_bbiu.php).
 *
 *   php database/import_lecturers.php storage/imports/lecturers_bbiu.php
 *   php database/import_lecturers.php <file> --dry-run     check everything, save nothing
 *
 * Each lecturer becomes a staff record (academic, in their college) with a
 * Lecturer login. People already imported are recognised by name and updated,
 * so the file can be corrected and re-run. New accounts get a temporary
 * password, printed once, that must be changed at first sign-in. Staff numbers
 * follow the Staff screen's format: <institution short name>/0001.
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
use App\Core\Hash;
use App\Services\AcademicCatalogSync;
use App\Support\PersonName;

$args   = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => !str_starts_with($a, '--')));
$dryRun = in_array('--dry-run', $argv, true);
if ($args === [] || !is_file($args[0])) {
    exit("Usage: php database/import_lecturers.php <data file> [--dry-run]\n");
}
$data = require $args[0];
echo "Lecturer import - " . basename($args[0]) . ($dryRun ? ' (dry run)' : '') . "\n" . str_repeat('=', 64) . "\n";

/* Schema (staff college column) and colleges must be current first. */
(new AcademicCatalogSync($base))->load(Database::connection(), false);

$colleges    = Database::select('SELECT code, id, name FROM faculties');
$collegeIds  = array_column($colleges, 'id', 'code');
$collegeName = array_column($colleges, 'name', 'code');
$lecturerRole = Database::scalar('SELECT id FROM roles WHERE slug = "lecturer"');
$prefix       = (string) (Database::scalar('SELECT setting_value FROM settings WHERE setting_key = "institution_short_name"') ?: 'STF');

$slug = static fn (string $s): string => (string) preg_replace('/[^a-z]/', '', strtolower(str_replace(['Jr.', 'Sr.'], '', $s)));

// Usernames: first initial + surname; where two people in the file share that, both add their middle initial.
$people = [];
foreach ($data['colleges'] as $code => $rows) {
    if (!isset($collegeIds[$code])) {
        exit("College {$code} is not in the system.\n");
    }
    foreach ($rows as $row) {
        $people[] = $row + ['college' => $code, 'base' => substr($slug($row['first']), 0, 1) . $slug($row['last'])];
    }
}
$baseCounts = array_count_values(array_column($people, 'base'));

$nextStaffNumber = static function () use ($prefix): string {
    $max = (int) Database::scalar(
        'SELECT COALESCE(MAX(CAST(SUBSTRING(staff_number, ?) AS UNSIGNED)), 0) FROM staff WHERE staff_number REGEXP ?',
        [strlen($prefix) + 2, '^' . preg_quote($prefix, '/') . '/[0-9]+$']
    );
    return $prefix . '/' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
};

$created = $updated = $failed = 0;
$perCollege = [];
$passwords  = [];
foreach ($people as $p) {
    $display = trim(($p['title'] ?? '') . ' ' . $p['first'] . ' ' . ($p['other'] ?? '') . ' ' . $p['last']);
    $display = (string) preg_replace('/\s+/', ' ', $display);
    try {
        Database::beginTransaction();

        // Already in the system? Same first and last name, compatible middle names ("P." = "Partee"),
        // whether imported as a lecturer or as administrative staff.
        $matches = PersonName::findStaff($p['first'], $p['last'], $p['other']);
        if (count($matches) > 1) {
            throw new RuntimeException('matches ' . count($matches) . ' existing staff records; resolve by hand');
        }
        $existing = $matches[0] ?? null;

        if ($existing !== null) {
            Database::statement(
                'UPDATE users SET title = ?, gender = COALESCE(?, gender), user_type = "lecturer", deleted_at = NULL WHERE id = ?',
                [$p['title'], $p['sex'], $existing['user_id']]
            );
            Database::statement(
                'UPDATE staff SET faculty_id = ?, designation = COALESCE(designation, ?) WHERE id = ?',
                [$collegeIds[$p['college']], $data['designation'], $existing['staff_id']]
            );
            $userId = (int) $existing['user_id'];
            [$username, $staffNumber, $action] = [$existing['username'], $existing['staff_number'], 'updated'];
        } else {
            $username = $p['base'];
            if ($baseCounts[$p['base']] > 1 && $p['other'] !== null) {
                $username = substr($slug($p['first']), 0, 1) . substr($slug($p['other']), 0, 1) . $slug($p['last']);
            }
            for ($n = 2, $candidate = $username; Database::scalar('SELECT id FROM users WHERE username = ?', [$candidate]); $n++) {
                $candidate = $username . $n;
            }
            $username = $candidate;

            $password = 'Bbiu-' . substr(bin2hex(random_bytes(6)), 0, 8);
            Database::statement(
                'INSERT INTO users (uuid, username, email, password_hash, title, first_name, last_name, other_name, gender,
                                    user_type, status, must_change_password)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, "lecturer", "active", 1)',
                [Hash::uuid4(), $username, Hash::make($password), $p['title'], $p['first'], $p['last'], $p['other'], $p['sex']]
            );
            $userId      = Database::lastInsertId();
            $staffNumber = $nextStaffNumber();
            Database::statement(
                'INSERT INTO staff (user_id, staff_number, department_id, faculty_id, designation, staff_category, status)
                 VALUES (?, ?, NULL, ?, ?, "academic", "active")',
                [$userId, $staffNumber, $collegeIds[$p['college']], $data['designation']]
            );
            $passwords[$username] = $password;
            $action = 'created';
        }
        if ($lecturerRole !== null) {
            Database::statement('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $lecturerRole]);
        }

        $dryRun ? Database::rollBack() : Database::commit();
        $action === 'created' ? $created++ : $updated++;
        $perCollege[$p['college']] = ($perCollege[$p['college']] ?? 0) + 1;
        printf("%-7s %-5s %-34s %-12s %s\n", strtoupper($action), $p['college'], $display, $username, $staffNumber);
        if ($p['note'] !== null) {
            echo "        note: {$p['note']}\n";
        }
    } catch (Throwable $e) {
        Database::rollBack();
        $failed++;
        echo "FAIL    {$p['college']}  {$display}: {$e->getMessage()}\n";
    }
}

echo str_repeat('-', 64) . "\n";
foreach ($data['colleges'] as $code => $rows) {
    printf("%-46s %2d of %2d\n", $collegeName[$code], $perCollege[$code] ?? 0, count($rows));
}
echo "{$created} created, {$updated} updated, {$failed} failed" . ($dryRun ? ' (dry run: nothing saved)' : '') . ".\n";
if ($passwords !== [] && !$dryRun) {
    echo "\nNew logins (temporary passwords, shown once; each must be changed at first sign-in):\n";
    foreach ($passwords as $username => $password) {
        echo "  {$username}  {$password}\n";
    }
}
exit($failed > 0 ? 1 : 0);
