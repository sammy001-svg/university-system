<?php
declare(strict_types=1);

namespace App\Services\DataImport;

use App\Core\Database;
use App\Support\PersonName;
use RuntimeException;
use Throwable;

/**
 * Staff (administrative, technical, support - or academic). Each row sets a
 * job title, staff category, system role and whether the person may sign in.
 * People already in the system (a lecturer who is also an administrator) are
 * recognised by name and updated; roles are only ever added, never removed.
 */
final class StaffImporter extends Importer
{
    public function run(array $data, ImportReport $report, bool $dryRun = false): void
    {
        $roleIds = array_column(Database::select('SELECT slug, id FROM roles'), 'id', 'slug');
        foreach ($data['staff'] as $row) {
            if ($row['role'] !== null && !isset($roleIds[$row['role']])) {
                throw new RuntimeException("Unknown role \"{$row['role']}\" for {$row['first']} {$row['last']}.");
            }
            if (!in_array($row['category'], ['academic', 'administrative', 'support', 'technical'], true)) {
                throw new RuntimeException("Unknown staff category \"{$row['category']}\" for {$row['first']} {$row['last']}.");
            }
        }

        $bases      = array_map(static fn (array $r): string => substr(self::slug($r['first']), 0, 1) . self::slug($r['last']), $data['staff']);
        $baseCounts = array_count_values($bases);

        foreach ($data['staff'] as $i => $p) {
            $display = (string) preg_replace('/\s+/', ' ', trim(($p['title'] ?? '') . ' ' . $p['first'] . ' ' . ($p['other'] ?? '') . ' ' . $p['last']));
            try {
                $this->begin();
                $notes   = $p['note'] !== null ? [$p['note']] : [];
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
                        $username = substr(self::slug($p['first']), 0, 1) . substr(self::slug($p['other']), 0, 1) . self::slug($p['last']);
                    }
                    $username    = self::freeUsername($username);
                    $userId      = self::createAccount($username, $p, 'staff', (bool) $p['login']);
                    $staffNumber = self::nextStaffNumber();
                    Database::statement(
                        'INSERT INTO staff (user_id, staff_number, department_id, faculty_id, designation, staff_category, status)
                         VALUES (?, ?, NULL, NULL, ?, ?, "active")',
                        [$userId, $staffNumber, $p['designation'], $p['category']]
                    );
                    $action = 'created';
                }

                self::grantRole($userId, $p['role']);
                $roles = (string) Database::scalar(
                    'SELECT GROUP_CONCAT(r.slug ORDER BY r.level SEPARATOR ", ") FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ?',
                    [$userId]
                );

                $this->finish($dryRun);
                $action === 'created' ? $report->created++ : $report->updated++;
                $report->line(sprintf('%-7s %-36s %-11s %-10s %-33s %s', strtoupper($action), $display, $username, $staffNumber,
                    $p['designation'], $p['login'] || $roles !== '' ? ($roles ?: 'sign-in, no role') : 'no sign-in'));
                foreach ($notes as $note) {
                    $report->note($note);
                }
            } catch (Throwable $e) {
                $this->abort();
                $report->failed++;
                $report->line("FAIL    {$display}: {$e->getMessage()}");
            }
        }
    }
}
