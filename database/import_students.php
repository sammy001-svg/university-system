<?php
/**
 * Import students and their semester registrations from a billing-form data
 * file (see storage/imports/), and raise their bills through the normal
 * billing rules.
 *
 *   php database/import_students.php storage/imports/billing_forms_2026_semester_two.php
 *   php database/import_students.php <file> --dry-run     check everything, save nothing
 *
 * For each student: the account and student record are created or updated
 * (matched by student ID), course codes are resolved through the catalogue and
 * its billing-form aliases, registrations are recorded, and the semester bill
 * is built. The bill must equal the totals printed on the form to the cent -
 * credits, tuition, registration fees, grand total and 1st installment -
 * otherwise that student is rolled back and reported. Safe to run again.
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
use App\Models\Invoice;
use App\Services\AcademicCatalogSync;
use App\Services\BillingService;

$args   = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => !str_starts_with($a, '--')));
$dryRun = in_array('--dry-run', $argv, true);
if ($args === [] || !is_file($args[0])) {
    exit("Usage: php database/import_students.php <data file> [--dry-run]\n");
}
$data = require $args[0];
echo "Student import - " . basename($args[0]) . ($dryRun ? ' (dry run)' : '') . "\n" . str_repeat('=', 60) . "\n";

/* The catalogue (courses, aliases, rates, fee schedules) must be current first. */
$pdo  = Database::connection();
$sync = new AcademicCatalogSync($base);
$sync->load($pdo, false);
echo "Academic catalogue up to date.\n";

$semester = Database::selectOne(
    'SELECT s.* FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id WHERE ay.name = ? AND s.name = ?',
    [$data['academic_year'], $data['semester']]
);
if ($semester === null) {
    exit("Semester \"{$data['semester']}\" of {$data['academic_year']} is not in the system.\n");
}
$semesterId  = (int) $semester['id'];
$studentRole = Database::scalar('SELECT id FROM roles WHERE slug = "student"');
$billing     = new BillingService();
$invoices    = new Invoice();

$parseStanding = static function (string $standing): array {
    if (!preg_match('/^(Freshman|Sophomore|Junior|Senior)\s+(I{1,2})$/i', trim($standing), $m)) {
        throw new RuntimeException("Unrecognised status \"{$standing}\" (expected e.g. Freshman I, Sophomore II)");
    }
    $years = ['freshman' => 1, 'sophomore' => 2, 'junior' => 3, 'senior' => 4];
    return [$years[strtolower($m[1])], strtoupper($m[2]) === 'II' ? 2 : 1];
};
$resolveCourse = static function (string $code): array {
    $course = Database::selectOne('SELECT id, code, title, credit_hours, tuition_rate_code FROM courses WHERE code = ?', [$code]);
    if ($course !== null) {
        return $course + ['via' => null];
    }
    $alias = Database::selectOne(
        'SELECT c.id, c.code, c.title, c.credit_hours, c.tuition_rate_code FROM course_aliases a JOIN courses c ON c.id = a.course_id WHERE a.code = ?',
        [$code]
    );
    if ($alias === null) {
        throw new RuntimeException("Course {$code} is not in the catalogue and has no alias");
    }
    return $alias + ['via' => $code];
};

$imported = 0;
$failed   = 0;
foreach ($data['students'] as $row) {
    $label = "{$row['id']}  {$row['last_name']}, {$row['first_name']}";
    try {
        Database::beginTransaction();
        $notes = [];

        $program = Database::selectOne('SELECT id, name FROM programs WHERE code = ?', [$row['program']]);
        if ($program === null) {
            throw new RuntimeException("Programme {$row['program']} is not in the system");
        }
        [$year, $term] = $parseStanding($row['standing']);

        /* Account: username is the student ID; e-mail only if the form gives one. */
        $username = strtolower($row['id']);
        $user     = Database::selectOne('SELECT id FROM users WHERE username = ?', [$username]);
        $password = null;
        if ($user === null) {
            $password = 'Bbiu-' . substr(bin2hex(random_bytes(6)), 0, 8);
            Database::statement(
                'INSERT INTO users (uuid, username, email, password_hash, first_name, last_name, other_name, gender, phone,
                                    user_type, status, must_change_password)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "student", "active", 1)',
                [Hash::uuid4(), $username, $row['email'], Hash::make($password), $row['first_name'], $row['last_name'],
                 $row['other_name'], $row['sex'], $row['phone']]
            );
            $userId = Database::lastInsertId();
            if ($studentRole !== null) {
                Database::statement('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $studentRole]);
            }
        } else {
            $userId = (int) $user['id'];
            Database::statement(
                'UPDATE users SET first_name = ?, last_name = ?, other_name = ?, gender = ?, phone = ?, email = COALESCE(?, email) WHERE id = ?',
                [$row['first_name'], $row['last_name'], $row['other_name'], $row['sex'], $row['phone'], $row['email'], $userId]
            );
        }

        /* Student record, matched by student ID. Country of origin stays empty when the form leaves it blank. */
        $student = Database::selectOne('SELECT id FROM students WHERE admission_number = ?', [$row['id']]);
        $values  = [$program['id'], $year, $term, $row['country']];
        if ($student === null) {
            Database::statement(
                'INSERT INTO students (user_id, admission_number, registration_number, program_id, year_of_study, current_semester,
                                       nationality, campus_id, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT id FROM campuses WHERE is_main = 1 LIMIT 1), "active")',
                array_merge([$userId, $row['id'], $row['id']], $values)
            );
            $studentId = Database::lastInsertId();
        } else {
            $studentId = (int) $student['id'];
            Database::statement(
                'UPDATE students SET program_id = ?, year_of_study = ?, current_semester = ?, nationality = ?, status = "active" WHERE id = ?',
                array_merge($values, [$studentId])
            );
        }

        /* Registrations for the semester, exactly as on the form. */
        $wanted = [];
        foreach ($row['courses'] as [$code, $title, $section, $credits, $cost]) {
            $course = $resolveCourse($code);
            $rate   = (float) Database::scalar(
                'SELECT COALESCE((SELECT rate_per_credit FROM tuition_rates WHERE code = ?),
                                 (SELECT rate_per_credit FROM tuition_rates WHERE code = "GENERAL"), 0)',
                [$course['tuition_rate_code']]
            );
            if (abs($credits * $rate - $cost) > 0.005) {
                throw new RuntimeException(sprintf('%s: form charges %s for %d credits; the %s rate gives %s',
                    $code, money($cost), $credits, $course['tuition_rate_code'] ?? 'GENERAL', money($credits * $rate)));
            }
            if ($course['via'] !== null) {
                $notes[] = "{$code} recorded as {$course['code']}";
            }

            $offeringId = Database::scalar(
                'SELECT id FROM course_offerings WHERE course_id = ? AND semester_id = ? AND section = ?',
                [$course['id'], $semesterId, $section]
            );
            if ($offeringId === null) {
                Database::statement(
                    'INSERT INTO course_offerings (course_id, semester_id, section, capacity, delivery_mode, status) VALUES (?, ?, ?, 60, "physical", "open")',
                    [$course['id'], $semesterId, $section]
                );
                $offeringId = Database::lastInsertId();
            }
            Database::statement(
                'INSERT INTO course_registrations (student_id, offering_id, semester_id, registration_type, credit_hours,
                                                   approval_status, approved_at, status, remarks)
                 VALUES (?, ?, ?, "normal", ?, "approved", NOW(), "registered", ?)
                 ON DUPLICATE KEY UPDATE credit_hours = VALUES(credit_hours), status = "registered", remarks = VALUES(remarks)',
                [$studentId, $offeringId, $semesterId, $credits, 'Billing form: ' . $code . ' ' . $title]
            );
            $wanted[] = (int) $offeringId;
        }
        // Anything registered earlier this semester that is not on the form is dropped.
        Database::statement(
            'UPDATE course_registrations SET status = "dropped"
              WHERE student_id = ? AND semester_id = ? AND offering_id NOT IN (' . implode(',', $wanted) . ')',
            [$studentId, $semesterId]
        );
        Database::statement(
            'UPDATE course_offerings o SET enrolled_count =
                (SELECT COUNT(*) FROM course_registrations cr WHERE cr.offering_id = o.id AND cr.status = "registered")
              WHERE o.id IN (' . implode(',', $wanted) . ')'
        );

        /* The bill, through the normal billing rules, checked against the paper form. */
        $result = $billing->refreshSemesterBill($studentId, $semesterId);
        if (!$result['ok']) {
            throw new RuntimeException($result['message']);
        }
        $form = $invoices->billingForm((int) $result['invoice_id']);
        $got  = [
            'credits'           => (int) $form['credits'],
            'tuition'           => $form['tuition'],
            'registration_fees' => $form['feesTotal'],
            'grand_total'       => round($form['tuition'] + $form['feesTotal'], 2),
            'first_installment' => (float) ($form['installments'][0]['amount'] ?? 0),
        ];
        foreach ($row['expected'] as $key => $expected) {
            if (abs((float) $got[$key] - (float) $expected) > 0.005) {
                throw new RuntimeException("{$key}: the form shows {$expected}, the system bill gives {$got[$key]}");
            }
        }
        if ($form['standing'] !== $row['standing']) {
            throw new RuntimeException("status: the form shows {$row['standing']}, the system gives {$form['standing']}");
        }

        $dryRun ? Database::rollBack() : Database::commit();
        $imported++;
        printf("OK    %-34s %-7s %-12s %s  tuition %s + fees %s = %s, 1st installment %s\n",
            $label, $row['program'], $row['standing'], $form['invoice']['invoice_number'],
            money($got['tuition']), money($got['registration_fees']), money($got['grand_total']), money($got['first_installment']));
        foreach ($notes as $note) {
            echo "        {$note}\n";
        }
        if ($password !== null && !$dryRun) {
            echo "        New login: username {$username}, temporary password {$password} (must be changed at first sign-in)\n";
        }
    } catch (Throwable $e) {
        Database::rollBack();
        $failed++;
        echo "FAIL  {$label}: {$e->getMessage()}\n";
    }
}

echo str_repeat('-', 60) . "\n{$imported} matched their billing form" . ($dryRun ? ' (nothing saved)' : ' and were saved') . ", {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
