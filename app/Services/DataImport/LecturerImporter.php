<?php
declare(strict_types=1);

namespace App\Services\DataImport;

use App\Core\Database;
use App\Support\PersonName;
use RuntimeException;
use Throwable;

/**
 * Lecturers by college. Each becomes an academic staff record in their
 * college with a Lecturer login. People already in the system are recognised
 * by name ("P." matches "Partee") and updated, never duplicated.
 */
final class LecturerImporter extends Importer
{
    public function run(array $data, ImportReport $report, bool $dryRun = false): void
    {
        $colleges   = Database::select('SELECT code, id, name FROM faculties');
        $collegeIds = array_column($colleges, 'id', 'code');
        $names      = array_column($colleges, 'name', 'code');

        // Usernames: first initial + surname; where two people in the file share that, both add their middle initial.
        $people = [];
        foreach ($data['colleges'] as $code => $rows) {
            if (!isset($collegeIds[$code])) {
                throw new RuntimeException("College {$code} is not in the system.");
            }
            foreach ($rows as $row) {
                $people[] = $row + ['college' => $code, 'base' => substr(self::slug($row['first']), 0, 1) . self::slug($row['last'])];
            }
        }
        $baseCounts = array_count_values(array_column($people, 'base'));

        $perCollege = [];
        foreach ($people as $p) {
            $display = (string) preg_replace('/\s+/', ' ', trim(($p['title'] ?? '') . ' ' . $p['first'] . ' ' . ($p['other'] ?? '') . ' ' . $p['last']));
            try {
                $this->begin();
                $matches = PersonName::findStaff($p['first'], $p['last'], $p['other']);
                if (count($matches) > 1) {
                    throw new RuntimeException('matches ' . count($matches) . ' existing staff records; resolve by hand');
                }

                if ($matches !== []) {
                    $m = $matches[0];
                    Database::statement(
                        'UPDATE users SET title = ?, gender = COALESCE(?, gender), user_type = "lecturer", deleted_at = NULL WHERE id = ?',
                        [$p['title'], $p['sex'], $m['user_id']]
                    );
                    Database::statement(
                        'UPDATE staff SET faculty_id = ?, designation = COALESCE(designation, ?) WHERE id = ?',
                        [$collegeIds[$p['college']], $data['designation'], $m['staff_id']]
                    );
                    [$userId, $username, $staffNumber, $action] = [(int) $m['user_id'], $m['username'], $m['staff_number'], 'updated'];
                } else {
                    $username = $p['base'];
                    if ($baseCounts[$p['base']] > 1 && $p['other'] !== null) {
                        $username = substr(self::slug($p['first']), 0, 1) . substr(self::slug($p['other']), 0, 1) . self::slug($p['last']);
                    }
                    $username    = self::freeUsername($username);
                    $userId      = self::createAccount($username, $p, 'lecturer');
                    $staffNumber = self::nextStaffNumber();
                    Database::statement(
                        'INSERT INTO staff (user_id, staff_number, department_id, faculty_id, designation, staff_category, status)
                         VALUES (?, ?, NULL, ?, ?, "academic", "active")',
                        [$userId, $staffNumber, $collegeIds[$p['college']], $data['designation']]
                    );
                    $action = 'created';
                }
                self::grantRole($userId, 'lecturer');

                $this->finish($dryRun);
                $action === 'created' ? $report->created++ : $report->updated++;
                $perCollege[$p['college']] = ($perCollege[$p['college']] ?? 0) + 1;
                $report->line(sprintf('%-7s %-5s %-34s %-12s %s', strtoupper($action), $p['college'], $display, $username, $staffNumber));
                if ($p['note'] !== null) {
                    $report->note($p['note']);
                }
            } catch (Throwable $e) {
                $this->abort();
                $report->failed++;
                $report->line("FAIL    {$p['college']}  {$display}: {$e->getMessage()}");
            }
        }
        foreach ($data['colleges'] as $code => $rows) {
            $report->summary($names[$code], sprintf('%2d of %2d', $perCollege[$code] ?? 0, count($rows)));
        }
    }
}
