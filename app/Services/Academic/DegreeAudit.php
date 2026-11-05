<?php
declare(strict_types=1);

namespace App\Services\Academic;

use App\Core\Database;
use App\Core\Setting;
use App\Models\FeeStructure;
use App\Models\Program;

/**
 * Checks a student's record against their programme's curriculum, term by
 * term: which requirements are complete, in progress, failed or still to take,
 * how many credits remain, and whether they can graduate.
 *
 * - A named course is met by passing it (any attempt; the best result counts).
 * - Either/or rows (same choice_group in a term) are met by passing one of them.
 * - Open slots (electives, minor, concentration) are filled by passed courses not
 *   used elsewhere: first those on the programme's elective/concentration list or
 *   the student's minor programme, then any other passed course for electives.
 */
final class DegreeAudit
{
    public static function for(int $studentId): ?array
    {
        $student = Database::selectOne(
            'SELECT s.*, u.first_name, u.last_name, u.other_name, p.id AS program_id, p.name AS program_name, p.code AS program_code,
                    p.total_credit_hours, p.duration_years, mp.name AS minor_name
               FROM students s JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
          LEFT JOIN programs mp ON mp.id = s.minor_program_id
              WHERE s.id = ?',
            [$studentId]
        );
        if ($student === null) {
            return null;
        }

        // Best published result per course, and current registrations.
        $results = [];
        foreach (Database::select(
            'SELECT r.course_id, c.code, c.title, r.grade, r.grade_point, r.outcome, r.credit_hours, r.total_score,
                    sem.name AS semester_name, ay.name AS academic_year
               FROM course_results r JOIN courses c ON c.id = r.course_id
               JOIN semesters sem ON sem.id = r.semester_id JOIN academic_years ay ON ay.id = sem.academic_year_id
              WHERE r.student_id = ? AND r.is_published = 1
              ORDER BY r.outcome = "pass" DESC, r.grade_point DESC',
            [$studentId]
        ) as $r) {
            $results[(int) $r['course_id']] ??= $r;
        }
        $inProgress = [];
        foreach (Database::select(
            'SELECT c.id, c.code, c.title FROM course_registrations cr JOIN course_offerings o ON o.id = cr.offering_id JOIN courses c ON c.id = o.course_id
               JOIN semesters sem ON sem.id = cr.semester_id
              WHERE cr.student_id = ? AND cr.status = "registered" AND (sem.is_current = 1 OR sem.results_published = 0)',
            [$studentId]
        ) as $r) {
            if (($results[(int) $r['id']]['outcome'] ?? null) !== 'pass') {
                $inProgress[(int) $r['id']] = $r;
            }
        }
        $passed = array_filter($results, static fn (array $r): bool => $r['outcome'] === 'pass');
        $used   = [];

        $statusOf = static function (?int $courseId) use ($passed, $inProgress, $results): string {
            return match (true) {
                $courseId === null              => 'open',
                isset($passed[$courseId])       => 'complete',
                isset($inProgress[$courseId])   => 'in_progress',
                isset($results[$courseId])      => 'failed',
                default                         => 'outstanding',
            };
        };

        // Lists that can fill open slots.
        $optionIds = static fn (int $programId, string $type): array => array_map('intval', array_column(Database::select(
            'SELECT course_id FROM program_course_options WHERE program_id = ? AND list_type = ? AND course_id IS NOT NULL', [$programId, $type]
        ), 'course_id'));
        $electiveIds = $optionIds((int) $student['program_id'], 'elective');
        $concentrationIds = $optionIds((int) $student['program_id'], 'concentration');
        $minorIds = $student['minor_program_id'] ? array_values(array_unique(array_merge(
            $optionIds((int) $student['minor_program_id'], 'minor'),
            array_map('intval', array_column(Database::select('SELECT course_id FROM program_courses WHERE program_id = ? AND course_id IS NOT NULL', [$student['minor_program_id']]), 'course_id'))
        ))) : [];

        $plan = (new Program())->curriculum((int) $student['program_id']);
        $terms = [];
        $openSlots = [];
        foreach ($plan as $year => $semesters) {
            foreach ($semesters as $semester => $rows) {
                $items = [];
                $groups = [];
                foreach ($rows as $row) {
                    if ($row['choice_group'] !== null && $row['course_id'] !== null) {
                        $groups[$row['choice_group']][] = $row;
                        continue;
                    }
                    $item = ['row' => $row, 'label' => $row['course_id'] ? $row['code'] . ' ' . $row['title'] : ($row['slot_label'] ?: ucfirst($row['course_type']) . ' course'),
                             'credits' => (int) $row['credit_hours'], 'type' => $row['course_type'], 'status' => $statusOf($row['course_id'] ? (int) $row['course_id'] : null),
                             'result' => $row['course_id'] ? ($results[(int) $row['course_id']] ?? null) : null, 'filled_by' => null];
                    if ($row['course_id']) {
                        $used[(int) $row['course_id']] = true;
                    } else {
                        $openSlots[] = [$year, $semester, count($items)];
                    }
                    $items[] = $item;
                }
                foreach ($groups as $alternatives) {
                    $best = null;
                    foreach ($alternatives as $alt) {
                        $used[(int) $alt['course_id']] = true;
                        $rank = ['complete' => 4, 'in_progress' => 3, 'failed' => 2, 'outstanding' => 1][$statusOf((int) $alt['course_id'])];
                        if ($best === null || $rank > $best[0]) {
                            $best = [$rank, $alt];
                        }
                    }
                    $alt = $best[1];
                    $items[] = ['row' => $alt, 'label' => implode(' or ', array_map(static fn (array $a): string => $a['code'], $alternatives)) . ($best[0] > 1 ? ' (' . $alt['code'] . ' ' . $alt['title'] . ')' : ''),
                                'credits' => (int) $alt['credit_hours'], 'type' => $alt['course_type'], 'status' => $statusOf((int) $alt['course_id']),
                                'result' => $results[(int) $alt['course_id']] ?? null, 'filled_by' => null];
                }
                $terms[$year][$semester] = $items;
            }
        }

        // Fill open slots with passed (then in-progress) courses not already counted.
        $spare = array_filter($passed, static fn (array $r, int $id): bool => !isset($used[$id]), ARRAY_FILTER_USE_BOTH);
        $spareRunning = array_filter($inProgress, static fn (array $r, int $id): bool => !isset($used[$id]), ARRAY_FILTER_USE_BOTH);
        foreach ($openSlots as [$year, $semester, $i]) {
            $slot = &$terms[$year][$semester][$i];
            $prefer = match ($slot['type']) {
                'minor'         => $minorIds,
                'concentration' => $concentrationIds,
                default         => $electiveIds,
            };
            $pick = null;
            foreach ([$spare, $spareRunning] as $k => $pool) {
                foreach ($pool as $id => $r) {
                    if (in_array($id, $prefer, true) || ($slot['type'] === 'elective' && $prefer === [])) {
                        $pick = [$k, $id, $r];
                        break 2;
                    }
                }
            }
            if ($pick === null && $slot['type'] === 'elective') {
                foreach ([$spare, $spareRunning] as $k => $pool) {
                    if ($pool !== []) {
                        $id = array_key_first($pool);
                        $pick = [$k, $id, $pool[$id]];
                        break;
                    }
                }
            }
            if ($pick !== null) {
                [$k, $id, $r] = $pick;
                $slot['status']    = $k === 0 ? 'complete' : 'in_progress';
                $slot['filled_by'] = $r['code'] . ' ' . $r['title'];
                $slot['result']    = $k === 0 ? $r : null;
                if ($k === 0) { unset($spare[$id]); } else { unset($spareRunning[$id]); }
            } else {
                $slot['status'] = 'outstanding';
            }
            unset($slot);
        }

        // Totals.
        $required = 0; $earned = 0; $running = 0; $counts = ['complete' => 0, 'in_progress' => 0, 'failed' => 0, 'outstanding' => 0];
        foreach ($terms as $semesters) {
            foreach ($semesters as $items) {
                foreach ($items as $item) {
                    if ($item['type'] === 'audit') {
                        continue;
                    }
                    $required += $item['credits'];
                    $counts[$item['status']]++;
                    if ($item['status'] === 'complete') { $earned += $item['credits']; }
                    if ($item['status'] === 'in_progress') { $running += $item['credits']; }
                }
            }
        }
        $required = max($required, (int) $student['total_credit_hours']);
        $gpa = Database::selectOne(
            'SELECT SUM(quality_points) AS qp, SUM(credit_hours) AS ch FROM course_results WHERE student_id = ? AND is_published = 1',
            [$studentId]
        );
        $cgpa      = (float) ($gpa['ch'] ?? 0) > 0 ? round((float) $gpa['qp'] / (float) $gpa['ch'], 2) : null;
        $minCgpa   = (float) Setting::get('graduation_min_cgpa', 2.0);
        $remaining = max(0, $required - $earned);
        $perTerm   = max(12, (int) round($required / max(1, (float) $student['duration_years'] * 2)));
        $termsLeft = (int) ceil(max(0, $remaining - $running) / $perTerm) + ($running > 0 ? 1 : 0);

        return [
            'student'    => $student,
            'standing'   => FeeStructure::classStanding($student),
            'terms'      => $terms,
            'required'   => $required,
            'earned'     => $earned,
            'running'    => $running,
            'remaining'  => $remaining,
            'percent'    => $required > 0 ? min(100, (int) round($earned / $required * 100)) : 0,
            'counts'     => $counts,
            'cgpa'       => $cgpa,
            'minCgpa'    => $minCgpa,
            'termsLeft'  => $remaining === 0 ? 0 : $termsLeft,
            'eligible'   => $counts['outstanding'] === 0 && $counts['failed'] === 0 && $counts['in_progress'] === 0 && $cgpa !== null && $cgpa >= $minCgpa,
            'extra'      => array_values($spare),
        ];
    }
}
