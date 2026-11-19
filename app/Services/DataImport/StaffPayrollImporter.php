<?php
declare(strict_types=1);

namespace App\Services\DataImport;

use App\Core\Database;
use App\Support\PersonName;
use Throwable;

/**
 * The payroll register's posting and contact details: telephone number, sex,
 * date of birth, position and the college or department a staff member is
 * posted to.
 *
 * This importer never creates a person. Staff come from the lecturer and
 * administrative staff lists; the payroll sheet writes some names differently,
 * so anyone it cannot match to exactly one existing record is reported and
 * left for HR to resolve by hand. Nothing already on a record is blanked: a
 * field is written only when the sheet has something to put there.
 */
final class StaffPayrollImporter extends Importer
{
    public function run(array $data, ImportReport $report, bool $dryRun = false): void
    {
        $faculties   = $this->lookup('SELECT id, name FROM faculties');
        $departments = $this->lookup('SELECT id, name FROM departments');

        $matched = 0;
        $created = 0;
        $missed  = [];
        foreach ($data['staff'] as $p) {
            $display = (string) preg_replace('/\s+/', ' ', trim($p['first'] . ' ' . ($p['other'] ?? '') . ' ' . $p['last']));
            try {
                $candidates = PersonName::findStaff($p['first'], $p['last'], $p['other']);
                if (count($candidates) > 1) {
                    $missed[] = $display . ' - ' . count($candidates) . ' staff records share that name';
                    $report->failed++;
                    $report->line(sprintf('%-9s %-34s matches %d records; resolve by hand', 'AMBIGUOUS', $display, count($candidates)));
                    continue;
                }
                if ($candidates === [] && !$p['create']) {
                    // The sheet names someone no earlier list has. Creating them here would
                    // risk a duplicate of a differently spelt name, so HR decides.
                    $missed[] = $display . ' - no staff record of that name';
                    $report->failed++;
                    $report->line(sprintf('%-9s %-34s %s', 'NO MATCH', $display, $p['position']));
                    continue;
                }

                $this->begin();
                if ($candidates === []) {
                    $match = $this->create($p);
                    $created++;
                    $report->created++;
                    $report->line(sprintf('%-9s %-34s %-11s %-30s %s', 'CREATED', $display, $match['staff_number'],
                        str_limit((string) $p['position'], 30), $p['phone'] ?? ''));
                } else {
                    $match = $candidates[0];
                }

                // Contact details: written only where the sheet gives one.
                Database::statement(
                    'UPDATE users SET phone = COALESCE(NULLIF(?, ""), phone),
                                      gender = COALESCE(?, gender),
                                      date_of_birth = COALESCE(?, date_of_birth)
                      WHERE id = ?',
                    [$p['phone'] ?? '', $p['sex'], $p['dob'], $match['user_id']]
                );

                $facultyId    = $p['college'] !== null ? ($faculties[$this->key($p['college'])] ?? null) : null;
                $departmentId = $p['department'] !== null ? ($departments[$this->key($p['department'])] ?? null) : null;
                Database::statement(
                    'UPDATE staff SET designation = COALESCE(NULLIF(?, ""), designation),
                                      faculty_id = COALESCE(?, faculty_id),
                                      department_id = COALESCE(?, department_id)
                      WHERE id = ?',
                    [$p['position'] ?? '', $facultyId, $departmentId, $match['staff_id']]
                );

                $this->finish($dryRun);
                if ($candidates !== []) {
                    $matched++;
                    $report->updated++;
                    $report->line(sprintf('%-9s %-34s %-11s %-30s %s', 'UPDATED', $display, $match['staff_number'],
                        str_limit((string) $p['position'], 30), $p['phone'] ?? ''));
                }

                if ($p['college'] !== null && $facultyId === null) {
                    $report->note('no college named "' . $p['college'] . '" in the catalogue; posting left as it was');
                }
                if ($p['note'] !== null) {
                    $report->note($p['note']);
                }
            } catch (Throwable $e) {
                $this->abort();
                $report->failed++;
                $report->line(sprintf('%-9s %-34s %s', 'FAILED', $display, $e->getMessage()));
            }
        }

        $report->summary('On the payroll sheet', (string) count($data['staff']));
        $report->summary('Matched to an existing staff record', (string) $matched);
        if ($created > 0) {
            $report->summary('Added from this sheet', (string) $created);
        }
        if ($missed !== []) {
            $report->summary('Needing HR to resolve', (string) count($missed));
            foreach ($missed as $line) {
                $report->note($line);
            }
        }
        $report->summary('Staff now with a telephone number', (string) (int) Database::scalar(
            'SELECT COUNT(*) FROM staff s JOIN users u ON u.id = s.user_id WHERE u.phone IS NOT NULL AND u.phone <> ""'
        ));
    }

    /**
     * Someone the sheet adds who is in no earlier list. The row has to say so
     * explicitly (create = true), so a spelling variant is never turned into a
     * second record by accident.
     *
     * @return array{staff_id:int, user_id:int, staff_number:string}
     */
    private function create(array $p): array
    {
        $username = self::freeUsername(substr(self::slug($p['first']), 0, 1) . self::slug($p['last']));
        $userId   = self::createAccount($username, $p, 'staff', (bool) $p['login']);
        $number   = self::nextStaffNumber();
        Database::statement(
            'INSERT INTO staff (user_id, staff_number, designation, staff_category, status) VALUES (?, ?, ?, ?, "active")',
            [$userId, $number, $p['position'], $p['category']]
        );
        $staffId = Database::lastInsertId();
        self::grantRole($userId, $p['role']);

        return ['staff_id' => $staffId, 'user_id' => $userId, 'staff_number' => $number];
    }

    /** @return array<string,int> normalised name => id */
    private function lookup(string $sql): array
    {
        $out = [];
        foreach (Database::select($sql) as $row) {
            $out[$this->key((string) $row['name'])] = (int) $row['id'];
        }
        return $out;
    }

    /** Names on the sheet are spelt loosely ("Adminstration", "Collee of Education"); compare on letters only. */
    private function key(string $name): string
    {
        return (string) preg_replace('/[^a-z]/', '', strtolower($name));
    }
}
