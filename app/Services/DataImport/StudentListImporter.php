<?php
declare(strict_types=1);

namespace App\Services\DataImport;

use App\Core\Database;
use App\Models\FeeStructure;
use App\Models\SemesterEnrollment;
use RuntimeException;
use Throwable;

/**
 * The registrar's semester student list: name, sex, ID, semester, major,
 * minor and registration stage (planned / made commitment / no commitment).
 *
 * - "Sem N" is the student's term in the programme: year = ceil(N/2), 1st/2nd semester of that year.
 * - Students already in the system (full BBIU<year><number> or short BBIU<number> ID) keep their ID.
 * - When two students share an ID on the list, the first keeps it and the next gets a provisional "-2" ID.
 */
final class StudentListImporter extends Importer
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
        $programIds = array_column(Database::select('SELECT code, id FROM programs'), 'id', 'code');

        /* 1. Check the whole list before saving anything. */
        $rows     = [];
        $problems = [];
        foreach ($data['stages'] as $stage => $list) {
            if (!isset(SemesterEnrollment::STAGES[$stage])) {
                $problems[] = "unknown stage {$stage}";
            }
            foreach ($list as $r) {
                [$sr, $name, $sex, $id, , $sem, $major, $minor] = $r;
                $rows[] = ['stage' => $stage, 'sr' => $sr, 'name' => $name, 'sex' => strtoupper($sex), 'id' => $id,
                           'sem' => $sem, 'major' => $major, 'minor' => $minor, 'note' => $r[8]];
                if (!isset($data['programs'][$major], $programIds[$data['programs'][$major]])) {
                    $problems[] = "#{$sr}: major \"{$major}\" is not mapped to a programme";
                }
                if ($minor !== null && !isset($data['programs'][$minor], $programIds[$data['programs'][$minor]])) {
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
        if (array_column($rows, 'sr') !== range(1, count($rows))) {
            $problems[] = 'row numbers are not 1..' . count($rows) . ' in order';
        }
        if ($problems !== []) {
            throw new RuntimeException("The list has problems; nothing was saved:\n  - " . implode("\n  - ", $problems));
        }

        /* Duplicate IDs on the list: the first keeps the ID, later ones get a provisional suffix. */
        $seen = [];
        foreach ($rows as &$row) {
            $seen[$row['id']][] = $row['sr'];
            $row['admission'] = 'BBIU' . $row['id'] . (count($seen[$row['id']]) > 1 ? '-' . count($seen[$row['id']]) : '');
        }
        unset($row);
        $sharedIds = array_filter($seen, static fn (array $srs): bool => count($srs) > 1);

        /* 2. Import. */
        $byStage = [];
        foreach ($rows as $r) {
            $name      = self::parseName($r['name']);
            $programId = (int) $programIds[$data['programs'][$r['major']]];
            $minorId   = $r['minor'] === null ? null : (int) $programIds[$data['programs'][$r['minor']]];
            $minorId   = $minorId === $programId ? null : $minorId;   // e.g. IT major with "IT" as minor
            $year      = (int) ceil($r['sem'] / 2);
            $term      = $r['sem'] % 2 === 1 ? 1 : 2;
            $sex       = $r['sex'] === 'M' ? 'male' : 'female';
            $notes     = $r['note'] !== null ? [$r['note']] : [];
            if (isset($sharedIds[$r['id']])) {
                $others  = array_diff($sharedIds[$r['id']], [$r['sr']]);
                $notes[] = "ID {$r['id']} is also listed for no. " . implode(', ', $others)
                         . (str_contains($r['admission'], '-') ? "; stored under the provisional ID {$r['admission']} until the registrar confirms" : '');
            }

            try {
                $this->begin();
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
                        [$sex, $name['first'], $name['last'], $name['other'], $existing['user_id']]
                    );
                    [$admission, $action] = [$existing['admission_number'], 'updated'];
                } else {
                    // Username = the ID; a provisional "-2" stays visible so it is not mistaken for another ID.
                    $userId = self::createAccount(strtolower($r['admission']), $name + ['sex' => $sex], 'student');
                    self::grantRole($userId, 'student');
                    Database::statement(
                        'INSERT INTO students (user_id, admission_number, registration_number, program_id, minor_program_id,
                                               year_of_study, current_semester, nationality, campus_id, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NULL, (SELECT id FROM campuses WHERE is_main = 1 LIMIT 1), "active")',
                        [$userId, $r['admission'], $r['admission'], $programId, $minorId, $year, $term]
                    );
                    $studentId = Database::lastInsertId();
                    [$admission, $action] = [$r['admission'], 'created'];
                }

                // The semester stage; the enrolment status itself still follows payment.
                Database::statement(
                    'INSERT INTO semester_enrollments (student_id, semester_id, status, registration_stage) VALUES (?, ?, "pending_payment", ?)
                     ON DUPLICATE KEY UPDATE registration_stage = VALUES(registration_stage)',
                    [$studentId, $semesterId, $r['stage']]
                );

                $this->finish($dryRun);
                $action === 'created' ? $report->created++ : $report->updated++;
                $byStage[$r['stage']] = ($byStage[$r['stage']] ?? 0) + 1;
                $report->line(sprintf('%3d %-7s %-16s %-30s %s %-7s %-14s %s', $r['sr'], strtoupper($action), $admission,
                    $name['last'] . ', ' . trim($name['first'] . ' ' . (string) $name['other']), $r['sex'],
                    $data['programs'][$r['major']], $minorId !== null ? 'minor ' . $data['programs'][$r['minor']] : '',
                    FeeStructure::classStanding(['year_of_study' => $year, 'current_semester' => $term])));
                foreach ($notes as $note) {
                    $report->note($note);
                }
            } catch (Throwable $e) {
                $this->abort();
                $report->failed++;
                $report->line(sprintf('%3d FAIL    ', $r['sr']) . "{$r['name']}: {$e->getMessage()}");
            }
        }
        foreach (SemesterEnrollment::STAGES as $stage => $label) {
            $report->summary($label, sprintf('%3d of %3d', $byStage[$stage] ?? 0, count($data['stages'][$stage] ?? [])));
        }
    }

    /** "Last, First Middle" -> parts, tidying stray dots ("M.." -> "M.", "Whayan." -> "Whayan"); keeps "Jr."/"Sr.". */
    public static function parseName(string $printed): array
    {
        [$last, $rest] = array_map('trim', explode(',', (string) preg_replace('/,+/', ',', $printed), 2)) + [1 => ''];
        $tokens = [];
        foreach (preg_split('/\s+/', trim($rest)) ?: [] as $t) {
            $t = (string) preg_replace('/\.{2,}/', '.', $t);
            if ($t === '' || $t === '.') {
                continue;
            }
            $letters = str_replace('.', '', $t);
            if (strlen($letters) <= 4 && (strlen($letters) === 1 || str_contains($t, '.')) && ctype_alpha($letters)
                && (strlen($letters) === 1 || substr_count($t, '.') >= strlen($letters) - 1)) {
                $tokens[] = strtoupper(implode('.', str_split($letters))) . '.';   // initials: "N" -> "N.", "A.S" -> "A.S."
            } else {
                $tokens[] = rtrim($t, '.');                                          // a word: "Whayan." -> "Whayan"
            }
        }
        return [
            'last'  => (string) preg_replace('/\s+\.+$/', '', trim($last)),
            'first' => array_shift($tokens) ?? '',
            'other' => $tokens === [] ? null : implode(' ', $tokens),
        ];
    }
}
