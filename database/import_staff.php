<?php
/**
 * Import staff (administrative, technical, support - or academic) from a data
 * file such as storage/imports/administrative_staff_bbiu.php.
 *
 *   php database/import_staff.php storage/imports/administrative_staff_bbiu.php
 *   php database/import_staff.php <file> --dry-run     check everything, save nothing
 *
 * Each row sets a person's job title, staff category, system role and whether
 * they may sign in. People already in the system (for example a lecturer who
 * is also an administrator) are recognised by name - a middle initial matches
 * the full middle name ("P." = "Partee") - and their existing record is
 * updated instead of being duplicated; roles are only ever added, never taken
 * away. New sign-in accounts get a temporary password, printed once, that must
 * be changed at first sign-in; staff without system access get an account that
 * cannot sign in. Staff numbers follow the Staff screen: <short name>/0001.
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
    exit("Usage: php database/import_staff.php <data file> [--dry-run]\n");
}
$data = require $args[0];
echo "Staff import - " . basename($args[0]) . ($dryRun ? ' (dry run)' : '') . "\n" . str_repeat('=', 72) . "\n";

(new AcademicCatalogSync($base))->load(Database::connection(), false);

$roleIds = array_column(Database::select('SELECT slug, id FROM roles'), 'id', 'slug');
foreach ($data['staff'] as $row) {
    if ($row['role'] !== null && !isset($roleIds[$row['role']])) {
        exit("Unknown role \"{$row['role']}\" for {$row['first']} {$row['last']}.\n");
    }
    if (!in_array($row['category'], ['academic', 'administrative', 'support', 'technical'], true)) {
        exit("Unknown staff category \"{$row['category']}\" for {$row['first']} {$row['last']}.\n");
    }
}
$prefix = (string) (Database::scalar('SELECT setting_value FROM settings WHERE setting_key = "institution_short_name"') ?: 'STF');

$slug = static fn (?string $s): string => (string) preg_replace('/[^a-z]/', '', strtolower(str_replace(['Jr.', 'Sr.'], '', (string) $s)));
$nextStaffNumber = static function () use ($prefix): string {
    $max = (int) Database::scalar(
        'SELECT COALESCE(MAX(CAST(SUBSTRING(staff_number, ?) AS UNSIGNED)), 0) FROM staff WHERE staff_number REGEXP ?',
        [strlen($prefix) + 2, '^' . preg_quote($prefix, '/') . '/[0-9]+$']
    );
    return $prefix . '/' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
};

$bases      = array_map(static fn (array $r): string => substr($slug($r['first']), 0, 1) . $slug($r['last']), $data['staff']);
$baseCounts = array_count_values($bases);

$created = $updated = $failed = 0;
$passwords = [];
foreach ($data['staff'] as $i => $p) {
    $display = (string) preg_replace('/\s+/', ' ', trim(($p['title'] ?? '') . ' ' . $p['first'] . ' ' . ($p['other'] ?? '') . ' ' . $p['last']));
    try {
        Database::beginTransaction();
        $notes = [];

        $matches = PersonName::findStaff($p['first'], $p['last'], $p['other']);
        if (count($matches) > 1) {
            throw new RuntimeException('matches ' . count($matches) . ' existing staff records; resolve by hand');
        }

        if ($matches !== []) {
            $m = $matches[0];
            // Keep the fuller middle name ("Partee" rather than "P.").
            $other = strlen((string) $p['other']) >= strlen((string) $m['other_name']) ? $p['other'] : $m['other_name'];
            Database::statement(
                'UPDATE users SET title = ?, other_name = ?, gender = COALESCE(?, gender) WHERE id = ?',
                [$p['title'], $other, $p['sex'], $m['user_id']]
            );
            Database::statement(
                'UPDATE staff SET designation = ?, staff_category = ?, status = "active" WHERE id = ?',
                [$p['designation'], $p['category'], $m['staff_id']]
            );
            if ($m['designation'] !== null && $m['designation'] !== $p['designation']) {
                $notes[] = "existing record updated (was \"{$m['designation']}\"); existing roles kept";
            }
            if (!$p['login'] && $m['status'] === 'active') {
                $notes[] = 'already has an active sign-in; left as it is';
            }
            [$userId, $username, $staffNumber, $action] = [(int) $m['user_id'], $m['username'], $m['staff_number'], 'updated'];
        } else {
            $username = $bases[$i];
            if ($baseCounts[$bases[$i]] > 1 && $p['other'] !== null) {
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
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, "staff", ?, 1)',
                [Hash::uuid4(), $username, Hash::make($password), $p['title'], $p['first'], $p['last'], $p['other'], $p['sex'],
                 $p['login'] ? 'active' : 'inactive']
            );
            $userId      = Database::lastInsertId();
            $staffNumber = $nextStaffNumber();
            Database::statement(
                'INSERT INTO staff (user_id, staff_number, department_id, faculty_id, designation, staff_category, status)
                 VALUES (?, ?, NULL, NULL, ?, ?, "active")',
                [$userId, $staffNumber, $p['designation'], $p['category']]
            );
            if ($p['login']) {
                $passwords[$username] = $password;
            }
            $action = 'created';
        }

        if ($p['role'] !== null) {
            Database::statement('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleIds[$p['role']]]);
        }
        $roles = (string) Database::scalar(
            'SELECT GROUP_CONCAT(r.slug ORDER BY r.level SEPARATOR ", ") FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
            [$userId]
        );

        $dryRun ? Database::rollBack() : Database::commit();
        $action === 'created' ? $created++ : $updated++;
        printf("%-7s %-36s %-11s %-10s %-33s %s\n", strtoupper($action), $display, $username, $staffNumber, $p['designation'],
            $p['login'] || $roles !== '' ? ($roles ?: 'sign-in, no role') : 'no sign-in');
        foreach (array_merge($p['note'] !== null ? [$p['note']] : [], $notes) as $note) {
            echo "        note: {$note}\n";
        }
    } catch (Throwable $e) {
        Database::rollBack();
        $failed++;
        echo "FAIL    {$display}: {$e->getMessage()}\n";
    }
}

echo str_repeat('-', 72) . "\n{$created} created, {$updated} updated, {$failed} failed" . ($dryRun ? ' (dry run: nothing saved)' : '') . ".\n";
if ($passwords !== [] && !$dryRun) {
    echo "\nNew logins (temporary passwords, shown once; each must be changed at first sign-in):\n";
    foreach ($passwords as $username => $password) {
        echo "  {$username}  {$password}\n";
    }
}
exit($failed > 0 ? 1 : 0);
