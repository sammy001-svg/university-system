<?php
declare(strict_types=1);

namespace App\Services\DataImport;

use App\Core\Database;
use App\Models\Invoice;
use App\Services\BillingService;
use RuntimeException;
use Throwable;

/**
 * Students from Student's Billing Forms: the student, their registered courses,
 * and their semester bill - which must equal the totals printed on the form
 * to the cent, or that student is rolled back and reported.
 */
final class BillingFormImporter extends Importer
{
    public function run(array $data, ImportReport $report, bool $dryRun = false): void
    {
        $semesterId = (int) Database::scalar(
            'SELECT s.id FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id WHERE ay.name = ? AND s.name = ?',
            [$data['academic_year'], $data['semester']]
        );
        if ($semesterId === 0) {
            throw new RuntimeException("Semester \"{$data['semester']}\" of {$data['academic_year']} is not in the system.");
        }
        $billing  = new BillingService();
        $invoices = new Invoice();

        foreach ($data['students'] as $row) {
            $label = "{$row['id']}  {$row['last_name']}, {$row['first_name']}";
            try {
                $this->begin();
                $notes   = [];
                $program = Database::selectOne('SELECT id FROM programs WHERE code = ?', [$row['program']]);
                if ($program === null) {
                    throw new RuntimeException("Programme {$row['program']} is not in the system");
                }
                [$year, $term] = self::parseStanding($row['standing']);
                $person = ['first' => $row['first_name'], 'last' => $row['last_name'], 'other' => $row['other_name'],
                           'sex' => $row['sex'], 'phone' => $row['phone'], 'email' => $row['email']];

                // The student may already exist under the full ID, or under the short ID a class list uses
                // ("BBIU100162" for BBIU2026100162); either way it is the same person.
                $shortId = preg_match('/^BBIU20\d{2}(\d+)$/', $row['id'], $m) ? 'BBIU' . $m[1] : $row['id'];
                $student = Database::selectOne(
                    'SELECT id, user_id, admission_number FROM students WHERE admission_number IN (?, ?) ORDER BY admission_number = ? DESC LIMIT 1',
                    [$row['id'], $shortId, $row['id']]
                );
                $values = [$program['id'], $year, $term, $row['country']];
                if ($student === null) {
                    $userId = (int) (Database::scalar('SELECT id FROM users WHERE username = ?', [strtolower($row['id'])])
                        ?: self::createAccount(strtolower($row['id']), $person, 'student'));
                    self::grantRole($userId, 'student');
                    Database::statement(
                        'INSERT INTO students (user_id, admission_number, registration_number, program_id, year_of_study, current_semester,
                                               nationality, campus_id, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT id FROM campuses WHERE is_main = 1 LIMIT 1), "active")',
                        array_merge([$userId, $row['id'], $row['id']], $values)
                    );
                    $studentId = Database::lastInsertId();
                    $action    = 'created';
                } else {
                    $studentId = (int) $student['id'];
                    $userId    = (int) $student['user_id'];
                    if ($student['admission_number'] !== $row['id']) {
                        $notes[] = "ID completed from {$student['admission_number']} to {$row['id']}";
                    }
                    Database::statement(
                        'UPDATE students SET admission_number = ?, registration_number = ?, program_id = ?, year_of_study = ?,
                                current_semester = ?, nationality = ?, status = "active" WHERE id = ?',
                        array_merge([$row['id'], $row['id']], $values, [$studentId])
                    );
                    $action = 'updated';
                }
                Database::statement(
                    'UPDATE users SET first_name = ?, last_name = ?, other_name = ?, gender = ?, phone = ?, email = COALESCE(?, email) WHERE id = ?',
                    [$row['first_name'], $row['last_name'], $row['other_name'], $row['sex'], $row['phone'], $row['email'], $userId]
                );

                // Registrations for the semester, exactly as on the form.
                $wanted = [];
                foreach ($row['courses'] as [$code, $title, $section, $credits, $cost]) {
                    $course = self::resolveCourse($code);
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

                // The bill, through the normal billing rules, checked against the paper form.
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

                $this->finish($dryRun);
                $action === 'created' ? $report->created++ : $report->updated++;
                $report->line(sprintf('%-7s %-34s %-7s %-12s %s  tuition %s + fees %s = %s, 1st installment %s',
                    strtoupper($action), $label, $row['program'], $row['standing'], $form['invoice']['invoice_number'],
                    money($got['tuition']), money($got['registration_fees']), money($got['grand_total']), money($got['first_installment'])));
                foreach ($notes as $note) {
                    $report->note($note);
                }
            } catch (Throwable $e) {
                $this->abort();
                $report->failed++;
                $report->line("FAIL    {$label}: {$e->getMessage()}");
            }
        }
    }

    /** "Freshman I" -> [1, 1], "Sophomore II" -> [2, 2]. */
    private static function parseStanding(string $standing): array
    {
        if (!preg_match('/^(Freshman|Sophomore|Junior|Senior)\s+(I{1,2})$/i', trim($standing), $m)) {
            throw new RuntimeException("Unrecognised status \"{$standing}\" (expected e.g. Freshman I, Sophomore II)");
        }
        $years = ['freshman' => 1, 'sophomore' => 2, 'junior' => 3, 'senior' => 4];
        return [$years[strtolower($m[1])], strtoupper($m[2]) === 'II' ? 2 : 1];
    }

    /** A course by its catalogue code, or by a billing-form alias (NURS 101 -> NSG 101). */
    private static function resolveCourse(string $code): array
    {
        $course = Database::selectOne('SELECT id, code, tuition_rate_code FROM courses WHERE code = ?', [$code]);
        if ($course !== null) {
            return $course + ['via' => null];
        }
        $alias = Database::selectOne(
            'SELECT c.id, c.code, c.tuition_rate_code FROM course_aliases a JOIN courses c ON c.id = a.course_id WHERE a.code = ?',
            [$code]
        );
        if ($alias === null) {
            throw new RuntimeException("Course {$code} is not in the catalogue and has no alias");
        }
        return $alias + ['via' => $code];
    }
}
