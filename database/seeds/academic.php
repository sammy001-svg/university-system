<?php
/**
 * Loads the university's academic catalogue (database/seeds/catalog/bbiu.php):
 * colleges, departments, programmes, courses, curricula, electives, minors,
 * concentrations, the grading scale, academic years and semesters.
 *
 * Idempotent: rows are matched by code (or name for academic years) and
 * updated in place. Each catalogue programme's curriculum and option lists are
 * replaced with the catalogue's, so the catalogue is the source of truth.
 *
 * Options:
 *   set_current  bool  also mark the semester that contains today as current (default false)
 */
declare(strict_types=1);

return static function (PDO $pdo, array $options = []): array {
    $catalog = require __DIR__ . '/catalog/bbiu.php';
    $today   = date('Y-m-d');

    /* ---------------- 1. Validate before touching the database ---------------- */
    $normalise = static fn (string $title): string =>
        trim((string) preg_replace('/[^a-z0-9]+/', ' ', strtolower(str_replace('&', ' and ', $title))));

    // Flatten every course occurrence: plan rows, electives, minors and concentrations.
    $occurrences = [];
    $visit = static function (array $row, int $year) use (&$occurrences, &$visit): void {
        if (isset($row['either'])) {
            foreach ($row['either'] as $alternative) {
                $visit($alternative, $year);
            }
            return;
        }
        if (isset($row['code'])) {
            $occurrences[$row['code']][] = ['title' => $row['title'], 'credits' => $row['credits'], 'year' => $year];
        }
    };
    foreach ($catalog['programs'] as $program) {
        foreach ($program['plan'] as $year => $semesters) {
            foreach ($semesters as $rows) {
                foreach ($rows as $row) {
                    $visit($row, (int) $year);
                }
            }
        }
        $lists = array_filter([$program['electives']['courses'] ?? null, $program['minor']['courses'] ?? null]);
        foreach ($program['concentrations'] ?? [] as $courses) {
            $lists[] = $courses;
        }
        foreach ($lists as $courses) {
            foreach ($courses as $row) {
                $visit($row, 0);
            }
        }
    }

    $problems = [];
    foreach ($occurrences as $code => $uses) {
        $titles = array_unique(array_map(static fn (array $u): string => $normalise($u['title']), $uses));
        if (count($titles) > 1 && !isset($catalog['canonical_titles'][$code])) {
            $problems[] = "{$code} has several titles: " . implode(' | ', array_unique(array_column($uses, 'title')));
        }
        $prefix = strtok($code, ' ');
        if (!isset($catalog['course_departments'][$code]) && !isset($catalog['subject_departments'][$prefix])) {
            $problems[] = "{$code}: no department owns the {$prefix} prefix";
        }
    }
    foreach ($catalog['programs'] as $programCode => $program) {
        if (!isset($catalog['departments'][$program['department']])) {
            $problems[] = "{$programCode}: unknown department {$program['department']}";
        }
        foreach ($program['plan'] as $year => $semesters) {
            if ($year < 1 || $year > $program['years']) {
                $problems[] = "{$programCode}: plan year {$year} outside its {$program['years']}-year duration";
            }
            foreach ($semesters as $semester => $rows) {
                $codes = [];
                foreach ($rows as $row) {
                    foreach (isset($row['either']) ? $row['either'] : [$row] as $item) {
                        if (isset($item['code'])) {
                            $codes[] = $item['code'];
                        }
                    }
                }
                foreach (array_unique(array_diff_assoc($codes, array_unique($codes))) as $duplicate) {
                    $problems[] = "{$programCode}: {$duplicate} appears twice in Year {$year} Semester {$semester}";
                }
            }
        }
    }
    if ($problems !== []) {
        throw new RuntimeException("Academic catalogue is inconsistent:\n  - " . implode("\n  - ", $problems));
    }

    /* ---------------- 2. Colleges and departments ---------------- */
    $upsertCollege = $pdo->prepare(
        'INSERT INTO faculties (code, name, status) VALUES (?, ?, "active")
         ON DUPLICATE KEY UPDATE name = VALUES(name), status = "active"'
    );
    foreach ($catalog['colleges'] as $code => $name) {
        $upsertCollege->execute([$code, $name]);
    }
    $collegeIds = $pdo->query('SELECT code, id FROM faculties')->fetchAll(PDO::FETCH_KEY_PAIR);

    $upsertDepartment = $pdo->prepare(
        'INSERT INTO departments (faculty_id, code, name, status) VALUES (?, ?, ?, "active")
         ON DUPLICATE KEY UPDATE faculty_id = VALUES(faculty_id), name = VALUES(name), status = "active"'
    );
    foreach ($catalog['departments'] as $code => [$college, $name]) {
        $upsertDepartment->execute([$collegeIds[$college], $code, $name]);
    }
    $departmentIds = $pdo->query('SELECT code, id FROM departments')->fetchAll(PDO::FETCH_KEY_PAIR);

    /* ---------------- 3. Courses ---------------- */
    $upsertCourse = $pdo->prepare(
        'INSERT INTO courses (department_id, code, title, credit_hours, level, status) VALUES (?, ?, ?, ?, ?, "active")
         ON DUPLICATE KEY UPDATE department_id = VALUES(department_id), title = VALUES(title),
                                 credit_hours = VALUES(credit_hours), level = VALUES(level), status = "active"'
    );
    foreach ($occurrences as $code => $uses) {
        // Catalogue credit hours: the value most programmes use (ties go to the higher value).
        $counts = array_count_values(array_column($uses, 'credits'));
        krsort($counts);
        arsort($counts);
        $credits = (int) array_key_first($counts);

        // Level from the course number (ENGL 101 -> 1); un-numbered courses take their plan year.
        $level = preg_match('/\s(\d)\d\d/', $code, $m) ? max(1, (int) $m[1]) : max(1, (int) max(array_column($uses, 'year')));

        $department = $catalog['course_departments'][$code] ?? $catalog['subject_departments'][strtok($code, ' ')];
        $upsertCourse->execute([
            $departmentIds[$department],
            $code,
            $catalog['canonical_titles'][$code] ?? $uses[0]['title'],
            $credits,
            min(9, $level),
        ]);
    }
    $courseIds = $pdo->query('SELECT code, id FROM courses')->fetchAll(PDO::FETCH_KEY_PAIR);
    $commonCourse = static fn (string $code): bool =>
        ($catalog['course_departments'][$code] ?? $catalog['subject_departments'][strtok($code, ' ')] ?? null) === 'GENS';

    /* ---------------- 4. Programmes, curricula and option lists ---------------- */
    $upsertProgram = $pdo->prepare(
        'INSERT INTO programs (department_id, code, name, award, level, duration_years, semesters_per_year,
                               total_credit_hours, study_mode, description, status)
         VALUES (?, ?, ?, ?, ?, ?, 2, ?, "full_time", ?, "active")
         ON DUPLICATE KEY UPDATE department_id = VALUES(department_id), name = VALUES(name), award = VALUES(award),
                                 level = VALUES(level), duration_years = VALUES(duration_years),
                                 semesters_per_year = VALUES(semesters_per_year), total_credit_hours = VALUES(total_credit_hours),
                                 description = VALUES(description), status = "active"'
    );
    $insertPlanRow = $pdo->prepare(
        'INSERT INTO program_courses (program_id, course_id, slot_label, year_of_study, semester_number, credit_hours,
                                      course_type, choice_group, sort_order, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertOption = $pdo->prepare(
        'INSERT INTO program_course_options (program_id, list_type, group_label, course_id, slot_label, credit_hours,
                                             choice_group, sort_order, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $stats = ['programs' => 0, 'plan_rows' => 0, 'option_rows' => 0, 'notes' => 0];
    foreach ($catalog['programs'] as $programCode => $program) {
        // Credits a student needs: one choice per either-group, every other row in full.
        $planCredits = 0;
        foreach ($program['plan'] as $semesters) {
            foreach ($semesters as $rows) {
                foreach ($rows as $row) {
                    $planCredits += isset($row['either']) ? min(array_column($row['either'], 'credits')) : $row['credits'];
                }
            }
        }

        $upsertProgram->execute([
            $departmentIds[$program['department']],
            $programCode,
            $program['name'],
            $program['award'],
            $program['level'],
            $program['years'],
            $program['credits'] ?? ($planCredits > 0 ? $planCredits : null),
            $program['description'] ?? null,
        ]);
        $programId = (int) $pdo->query('SELECT id FROM programs WHERE code = ' . $pdo->quote($programCode))->fetchColumn();
        $stats['programs']++;

        $pdo->prepare('DELETE FROM program_courses WHERE program_id = ?')->execute([$programId]);
        $pdo->prepare('DELETE FROM program_course_options WHERE program_id = ?')->execute([$programId]);

        foreach ($program['plan'] as $year => $semesters) {
            foreach ($semesters as $semesterNumber => $rows) {
                $sort  = 0;
                $group = 0;
                foreach ($rows as $row) {
                    $alternatives = isset($row['either']) ? $row['either'] : [$row];
                    $choice       = isset($row['either']) ? ++$group : null;
                    foreach ($alternatives as $item) {
                        $isSlot = isset($item['slot']);
                        $insertPlanRow->execute([
                            $programId,
                            $isSlot ? null : $courseIds[$item['code']],
                            $isSlot ? $item['slot'] : null,
                            $year,
                            $semesterNumber,
                            $item['credits'],
                            $isSlot ? $item['type'] : ($commonCourse($item['code']) ? 'common' : 'core'),
                            $choice,
                            ++$sort,
                            $item['note'],
                        ]);
                        $stats['plan_rows']++;
                        $stats['notes'] += $item['note'] !== null ? 1 : 0;
                    }
                }
            }
        }

        $lists = [];
        if (isset($program['electives'])) {
            $lists[] = ['elective', $program['electives']['label'], $program['electives']['courses']];
        }
        if (isset($program['minor'])) {
            $lists[] = ['minor', $program['minor']['label'], $program['minor']['courses']];
        }
        foreach ($program['concentrations'] ?? [] as $label => $courses) {
            $lists[] = ['concentration', $label, $courses];
        }
        foreach ($lists as [$type, $label, $rows]) {
            $sort  = 0;
            $group = 0;
            foreach ($rows as $row) {
                $alternatives = isset($row['either']) ? $row['either'] : [$row];
                $choice       = isset($row['either']) ? ++$group : null;
                foreach ($alternatives as $item) {
                    $isSlot = isset($item['slot']);
                    $insertOption->execute([
                        $programId, $type, $label,
                        $isSlot ? null : $courseIds[$item['code']],
                        $isSlot ? $item['slot'] : null,
                        $item['credits'], $choice, ++$sort, $item['note'],
                    ]);
                    $stats['option_rows']++;
                    $stats['notes'] += $item['note'] !== null ? 1 : 0;
                }
            }
        }
    }

    /* ---------------- 5. Grading scale ---------------- */
    $grades = array_column($catalog['grading'], 0);
    $pdo->exec('DELETE FROM grade_scales WHERE grade NOT IN (' . implode(',', array_map([$pdo, 'quote'], $grades)) . ')');
    $upsertGrade = $pdo->prepare(
        'INSERT INTO grade_scales (grade, min_score, max_score, grade_point, remarks, is_pass) VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE min_score = VALUES(min_score), max_score = VALUES(max_score),
                                 grade_point = VALUES(grade_point), remarks = VALUES(remarks), is_pass = VALUES(is_pass)'
    );
    foreach ($catalog['grading'] as $band) {
        $upsertGrade->execute($band);
    }
    $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = "pass_mark"')->execute([(string) $catalog['pass_mark']]);

    /* ---------------- 6. Academic years and semesters ---------------- */
    $upsertYear = $pdo->prepare(
        'INSERT INTO academic_years (name, start_date, end_date, is_current, status) VALUES (?, ?, ?, 0, ?)
         ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date), status = VALUES(status)'
    );
    $phase = static fn (string $start, string $end): string =>
        $end < $today ? 'closed' : ($start <= $today ? 'active' : 'planned');
    foreach ($catalog['academic_years'] as $name) {
        $upsertYear->execute([$name, "{$name}-01-01", "{$name}-12-31", $phase("{$name}-01-01", "{$name}-12-31")]);
    }
    $yearIds = $pdo->query('SELECT name, id FROM academic_years')->fetchAll(PDO::FETCH_KEY_PAIR);

    $upsertSemester = $pdo->prepare(
        'INSERT INTO semesters (academic_year_id, name, semester_number, start_date, end_date, registration_start,
                                registration_end, exam_start, exam_end, is_current, results_published, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), start_date = VALUES(start_date), end_date = VALUES(end_date),
                                 registration_start = VALUES(registration_start), registration_end = VALUES(registration_end),
                                 exam_start = VALUES(exam_start), exam_end = VALUES(exam_end), status = VALUES(status)'
    );
    foreach ($catalog['semesters'] as [$year, $name, $number, $start, $end, $regStart, $regEnd, $examStart, $examEnd]) {
        $upsertSemester->execute([$yearIds[$year], $name, $number, $start, $end, $regStart, $regEnd, $examStart, $examEnd, $phase($start, $end)]);
    }

    if (!empty($options['set_current'])) {
        // The semester in session today, else the latest one that has started.
        $current = $pdo->query(
            "SELECT id, academic_year_id FROM semesters WHERE start_date <= '{$today}' ORDER BY start_date DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if ($current !== false) {
            $pdo->exec('UPDATE semesters SET is_current = (id = ' . (int) $current['id'] . ')');
            $pdo->exec('UPDATE academic_years SET is_current = (id = ' . (int) $current['academic_year_id'] . ')');
        }
    }

    return $stats + [
        'colleges'    => count($catalog['colleges']),
        'departments' => count($catalog['departments']),
        'courses'     => count($occurrences),
        'years'       => count($catalog['academic_years']),
        'semesters'   => count($catalog['semesters']),
    ];
};
