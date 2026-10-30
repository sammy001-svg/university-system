<?php
/**
 * Import the registrar's semester student list (names, sex, ID, semester,
 * major, minor and registration stage) - see storage/imports/students_2026_semester_two_list.php.
 *
 *   php database/import_student_list.php storage/imports/students_2026_semester_two_list.php
 *   php database/import_student_list.php <file> --dry-run     check everything, save nothing
 *
 * - Students already in the system are matched by ID (BBIU + number, or the
 *   full BBIU<year><number> form) and keep their full ID.
 * - New students are stored as BBIU + the listed number, with a student login
 *   whose temporary password is printed once and must be changed at first sign-in.
 * - "Sem N" is the student's term in the programme: year = ceil(N/2), 1st/2nd semester of that year.
 * - When the list gives one ID to two students, the first keeps it and the next
 *   gets a provisional "-2" ID, flagged for the registrar.
 * - Each student's stage for the semester (planned / made commitment / no
 *   commitment) is recorded; enrolment itself still follows payment.
 * Safe to run again: records are updated in place.
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
use App\Models\FeeStructure;
use App\Models\SemesterEnrollment;
use App\Services\AcademicCatalogSync;

$args   = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => !str_starts_with($a, '--')));
$dryRun = in_array('--dry-run', $argv, true);
if ($args === [] || !is_file($args[0])) {
    exit("Usage: php database/import_student_list.php <data file> [--dry-run]\n");
}
$data = require $args[0];
echo "Student list import - " . basename($args[0]) . ($dryRun ? ' (dry run)' : '') . "\n" . str_repeat('=', 78) . "\n";

(new AcademicCatalogSync($base))->load(Database::connection(), false);

$semester = Database::selectOne(
    'SELECT s.id FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id WHERE ay.name = ? AND s.name = ?',
    [$data['academic_year'], $data['semester']]
);
if ($semester === null) {
    exit("Semester \"{$data['semester']}\" of {$data['academic_year']} is not in the system.\n");
}
$semesterId  = (int) $semester['id'];
$programIds  = array_column(Database::select('SELECT code, id FROM programs'), 'id', 'code');
$studentRole = Database::scalar('SELECT id FROM roles WHERE slug = "student"');

/* ---------------- 1. Check the whole list before saving anything ---------------- */
$rows     = [];
$problems = [];
foreach ($data['stages'] as $stage => $list) {
    if (!isset(SemesterEnrollment::STAGES[$stage])) {
        $problems[] = "unknown stage {$stage}";
    }
    foreach ($list as $r) {
        [$sr, $name, $sex, $id, , $sem, $major, $minor] = $r;
        $rows[] = ['stage' => $stage, 'sr' => $sr, 'name' => $name, 'sex' => strtoupper($sex), 'id' => $id, 'printed_sem' => $r[4],
                   'sem' => $sem, 'major' => $major, 'minor' => $minor, 'note' => $r[8]];
        if (!isset($data['programs'][$major]) || !isset($programIds[$data['programs'][$major]])) {
            $problems[] = "#{$sr}: major \"{$major}\" is not mapped to a programme";
        }
        if ($minor !== null && (!isset($data['programs'][$minor]) || !isset($programIds[$data['programs'][$minor]]))) {
            $problems[] = "#{$sr}: minor \"{$minor}\" is not mapped to a programme";
        }
        if (!in_array(strtoupper($sex), ['M', 'F'], true)) {
            $problems[] = "#{$sr}: sex \"{$sex}\"";
        }
        if ($sem < 1 || $sem > 10 || !preg_match('/^\d{5,6}$/', $id)) {
            $problems[] = "#{$sr}: semester {$sem} / ID {$id}";
        }
    }
}
$numbers = array_column($rows, 'sr');
if ($numbers !== range(1, count($rows))) {
    $problems[] = 'row numbers are not 1..' . count($rows) . ' in order';
}
if ($problems !== []) {
    exit("The list has problems; nothing was saved:\n  - " . implode("\n  - ", $problems) . "\n");
}

/* Names: "Last, First Middle" -> parts, tidying stray dots ("M.." -> "M.", "Whayan." -> "Whayan"). */
$parseName = static function (string $printed): array {
    [$last, $rest] = array_map('trim', explode(',', preg_replace('/,+/', ',', $printed), 2)) + [1 => ''];
    $tokens = [];
    foreach (preg_split('/\s+/', trim($rest)) ?: [] as $t) {
        $t = (string) preg_replace('/\.{2,}/', '.', $t);
        if ($t === '' || $t === '.') {
            continue;
        }
        $letters = str_replace('.', '', $t);
        if (strlen($letters) <= 4 && (strlen($letters) === 1 || str_contains($t, '.')) && ctype_alpha($letters) && (strlen($letters) === 1 || substr_count($t, '.') >= strlen($letters) - 1)) {
            $tokens[] = strtoupper(implode('.', str_split($letters))) . '.';   // initials: "N" -> "N.", "A.S" -> "A.S."
        } else {
            $tokens[] = rtrim($t, '.');                                          // a word: "Whayan." -> "Whayan"
        }
    }
    return ['last' => (string) preg_replace('/\s+\.+$/', '', trim($last)), 'first' => array_shift($tokens) ?? '', 'other' => $tokens === [] ? null : implode(' ', $tokens)];
};

/* Duplicate IDs on the list: the first keeps the ID, later ones get a provisional suffix. */
$seen = [];
foreach ($rows as &$row) {
    $seen[$row['id']][] = $row['sr'];
    $row['admission'] = 'BBIU' . $row['id'] . (count($seen[$row['id']]) > 1 ? '-' . count($seen[$row['id']]) : '');
}
unset($row);
$sharedIds = array_filter($seen, static fn (array $srs): bool => count($srs) > 1);

/* ---------------- 2. Import ---------------- */
$created = $updated = $failed = 0;
$byStage = [];
$passwords = [];
foreach ($rows as $r) {
    $name     = $parseName($r['name']);
    $programId = (int) $programIds[$data['programs'][$r['major']]];
    $minorId   = $r['minor'] === null ? null : (int) $programIds[$data['programs'][$r['minor']]];
    $minorId   = $minorId === $programId ? null : $minorId;   // e.g. IT major with "IT" as minor
    $year      = (int) ceil($r['sem'] / 2);
    $term      = $r['sem'] % 2 === 1 ? 1 : 2;
    $notes     = $r['note'] !== null ? [$r['note']] : [];
    if (isset($sharedIds[$r['id']])) {
        $others  = array_diff($sharedIds[$r['id']], [$r['sr']]);
        $notes[] = "ID {$r['id']} is also listed for no. " . implode(', ', $others)
                 . (str_contains($r['admission'], '-') ? "; stored under the provisional ID {$r['admission']} until the registrar confirms" : '');
    }

    try {
        Database::beginTransaction();
        $existing = Database::selectOne(
            'SELECT s.id, s.user_id, s.admission_number, s.program_id FROM students s
              WHERE s.admission_number = ? OR (s.admission_number REGEXP ? AND ? NOT LIKE "%-%")',
            [$r['admission'], '^BBIU20[0-9]{2}' . $r['id'] . '$', $r['admission']]
        );

        if ($existing !== null) {
            $studentId = (int) $existing['id'];
            if ((int) $existing['program_id'] !== $programId) {
                $notes[] = 'programme changed to match the list';
            }
            Database::statement(
                'UPDATE students SET program_id = ?, minor_program_id = ?, year_of_study = ?, current_semester = ?, status = "active" WHERE id = ?',
                [$programId, $minorId, $year, $term, $studentId]
            );
            Database::statement(
                'UPDATE users SET gender = ?, first_name = ?, last_name = ?, other_name = COALESCE(?, other_name) WHERE id = ?',
                [$r['sex'] === 'M' ? 'male' : 'female', $name['first'], $name['last'], $name['other'], $existing['user_id']]
            );
            [$admission, $action] = [$existing['admission_number'], 'updated'];
        } else {
            $username = strtolower($r['admission']);   // keeps the "-2" of a provisional ID visible
            $password = 'Bbiu-' . substr(bin2hex(random_bytes(6)), 0, 8);
            Database::statement(
                'INSERT INTO users (uuid, username, email, password_hash, first_name, last_name, other_name, gender,
                                    user_type, status, must_change_password)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, "student", "active", 1)',
                [Hash::uuid4(), $username, Hash::make($password), $name['first'], $name['last'], $name['other'],
                 $r['sex'] === 'M' ? 'male' : 'female']
            );
            $userId = Database::lastInsertId();
            if ($studentRole !== null) {
                Database::statement('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $studentRole]);
            }
            Database::statement(
                'INSERT INTO students (user_id, admission_number, registration_number, program_id, minor_program_id,
                                       year_of_study, current_semester, nationality, campus_id, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NULL, (SELECT id FROM campuses WHERE is_main = 1 LIMIT 1), "active")',
                [$userId, $r['admission'], $r['admission'], $programId, $minorId, $year, $term]
            );
            $studentId = Database::lastInsertId();
            $passwords[$username] = $password;
            [$admission, $action] = [$r['admission'], 'created'];
        }

        // The semester stage; the enrolment status itself still follows payment.
        Database::statement(
            'INSERT INTO semester_enrollments (student_id, semester_id, status, registration_stage) VALUES (?, ?, "pending_payment", ?)
             ON DUPLICATE KEY UPDATE registration_stage = VALUES(registration_stage)',
            [$studentId, $semesterId, $r['stage']]
        );

        $dryRun ? Database::rollBack() : Database::commit();
        $action === 'created' ? $created++ : $updated++;
        $byStage[$r['stage']] = ($byStage[$r['stage']] ?? 0) + 1;
        $display = $name['last'] . ', ' . trim($name['first'] . ' ' . (string) $name['other']);
        $standing = FeeStructure::classStanding(['year_of_study' => $year, 'current_semester' => $term]);
        printf("%3d %-7s %-16s %-30s %s %-7s %-14s %s\n", $r['sr'], strtoupper($action), $admission, $display, $r['sex'],
            $data['programs'][$r['major']], $minorId !== null ? 'minor ' . $data['programs'][$r['minor']] : '', $standing);
        foreach ($notes as $note) {
            echo "        note: {$note}\n";
        }
    } catch (Throwable $e) {
        Database::rollBack();
        $failed++;
        echo sprintf('%3d FAIL    ', $r['sr']) . "{$r['name']}: {$e->getMessage()}\n";
    }
}

echo str_repeat('-', 78) . "\n";
foreach (SemesterEnrollment::STAGES as $stage => $label) {
    printf("%-32s %3d of %3d\n", $label, $byStage[$stage] ?? 0, count($data['stages'][$stage] ?? []));
}
echo "{$created} created, {$updated} updated, {$failed} failed" . ($dryRun ? ' (dry run: nothing saved)' : '') . ".\n";
if ($passwords !== [] && !$dryRun) {
    echo "\nNew student logins (username = student ID; temporary passwords, shown once; must be changed at first sign-in):\n";
    foreach ($passwords as $username => $password) {
        echo "  {$username}  {$password}\n";
    }
}
exit($failed > 0 ? 1 : 0);
