<?php
/**
 * Demonstration data: a small but complete institution so every screen has
 * something meaningful to show.
 */
declare(strict_types=1);

return static function (PDO $pdo): void {

    $exists = static fn (string $table): bool =>
        (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn() > 0;

    if ($exists('students')) {
        echo "  Demo data already present - skipping.\n";
        return;
    }

    $hash = static fn (string $plain): string => App\Core\Hash::make($plain);
    $uuid = static fn (): string => App\Core\Hash::uuid4();

    /* ---------------- Campus, buildings, rooms ---------------- */
    $pdo->exec(
        "INSERT INTO campuses (code, name, address, city, country, phone, email, is_main, status)
         VALUES ('MAIN', 'Main Campus', 'Tubman Boulevard, Sinkor', 'Monrovia', 'Liberia', '+231 77 000 0001', 'main@bestbrain.edu.lr', 1, 'active'),
                ('TOWN', 'Town Campus', 'Broad Street', 'Monrovia', 'Liberia', '+231 77 000 0002', 'town@bestbrain.edu.lr', 0, 'active')"
    );
    $pdo->exec(
        "INSERT INTO buildings (campus_id, code, name, floors, status) VALUES
           (1, 'BLA', 'Science Complex', 4, 'active'),
           (1, 'BLB', 'Business Tower', 5, 'active'),
           (1, 'BLC', 'Library Block', 3, 'active'),
           (2, 'BLD', 'Town Annex', 6, 'active')"
    );

    $roomInsert = $pdo->prepare(
        'INSERT INTO rooms (building_id, code, name, floor, capacity, room_type, has_projector, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "available")'
    );
    $roomPlan = [
        [1, 'LH-101', 'Lecture Hall 101', 1, 120, 'lecture_hall', 1],
        [1, 'LH-102', 'Lecture Hall 102', 1, 90, 'lecture_hall', 1],
        [1, 'LAB-201', 'Computer Lab 201', 2, 45, 'laboratory', 1],
        [1, 'LAB-202', 'Physics Lab 202', 2, 40, 'laboratory', 0],
        [2, 'LH-301', 'Business Auditorium', 3, 200, 'lecture_hall', 1],
        [2, 'TR-302', 'Tutorial Room 302', 3, 35, 'tutorial', 0],
        [3, 'EX-001', 'Examination Hall A', 0, 300, 'exam_hall', 0],
        [4, 'LH-401', 'Town Hall 401', 4, 80, 'lecture_hall', 1],
    ];
    foreach ($roomPlan as $room) {
        $roomInsert->execute($room);
    }

    /* ---------------- Academic structure and calendar ---------------- */
    // Colleges, departments, programmes, courses, curricula, academic years and
    // semesters come from the academic catalogue (database/seeds/academic.php),
    // which the installer loads before this seed.
    $departmentIds = $pdo->query('SELECT code, id FROM departments')->fetchAll(PDO::FETCH_KEY_PAIR);
    $facultyIds    = $pdo->query('SELECT code, id FROM faculties')->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!isset($departmentIds['IT'], $facultyIds['CIT'])) {
        echo "  Academic catalogue not loaded - skipping demo data.\n";
        return;
    }
    $pdo->exec('UPDATE faculties SET campus_id = 1 WHERE campus_id IS NULL');

    // A planned 2027 year so the admissions pipeline has an open intake.
    $pdo->exec("INSERT IGNORE INTO academic_years (name, start_date, end_date, is_current, status)
                VALUES ('2027', '2027-01-01', '2027-12-31', 0, 'planned')");
    $yearIds = $pdo->query('SELECT name, id FROM academic_years')->fetchAll(PDO::FETCH_KEY_PAIR);
    $pdo->prepare(
        "INSERT INTO intakes (academic_year_id, name, code, start_date, application_open, application_close, status) VALUES
           (?, 'February 2026 Intake', 'FEB2026', '2026-02-02', '2025-10-01', '2026-01-23', 'closed'),
           (?, 'August 2026 Intake', 'AUG2026', '2026-08-03', '2026-04-01', '2026-07-24', 'closed'),
           (?, 'February 2027 Intake', 'FEB2027', '2027-02-01', '2026-09-01', '2027-01-22', 'open')"
    )->execute([$yearIds['2026'], $yearIds['2026'], $yearIds['2027']]);

    /* ---------------- Staff ---------------- */
    $userInsert = $pdo->prepare(
        'INSERT INTO users (uuid, username, email, password_hash, title, first_name, last_name, gender,
                            date_of_birth, phone, user_type, status, email_verified_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", NOW())'
    );
    $staffInsert = $pdo->prepare(
        'INSERT INTO staff (user_id, staff_number, department_id, designation, staff_category, employment_type,
                            qualification, specialization, date_joined, basic_salary, national_id, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active")'
    );
    $roleIds = $pdo->query('SELECT slug, id FROM roles')->fetchAll(PDO::FETCH_KEY_PAIR);
    $assignRole = $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)');

    $staffSeed = [
        // username, first, last, title, gender, department code, designation, category, qualification, salary, role
        ['registrar', 'Korto', 'Wesseh', 'Dr', 'female', null, 'University Registrar', 'administrative', 'PhD Education Management', 1900, 'registrar'],
        ['bursar', 'Emmanuel', 'Kollie', 'CPA', 'male', 'ACCT', 'Finance Officer', 'administrative', 'CPA, MBA Finance', 1750, 'finance-officer'],
        ['vtubman', 'Varney', 'Tubman', 'Dr', 'male', 'IT', 'Senior Lecturer', 'academic', 'PhD Computer Science', 1500, 'lecturer'],
        ['mfreeman', 'Massa', 'Freeman', 'Prof', 'female', 'IT', 'Professor', 'academic', 'PhD Software Engineering', 2200, 'hod'],
        ['agaye', 'Alfred', 'Gaye', 'Mr', 'male', 'GENS', 'Lecturer', 'academic', 'MSc Applied Mathematics', 1100, 'lecturer'],
        ['hnyanti', 'Hawa', 'Nyanti', 'Dr', 'female', 'ACCT', 'Senior Lecturer', 'academic', 'PhD Accounting', 1520, 'lecturer'],
        ['bdahn', 'Bendu', 'Dahn', 'Ms', 'female', null, 'University Librarian', 'administrative', 'MSc Information Science', 1050, 'librarian'],
        ['ptoe', 'Prince', 'Toe', 'Mr', 'male', null, 'Hostel Warden', 'support', 'BA Social Work', 700, 'warden'],
        ['mbrownell', 'Miatta', 'Brownell', 'Ms', 'female', null, 'HR Officer', 'administrative', 'BCom Human Resources', 950, 'hr-officer'],
        ['fkamara', 'Fatu', 'Kamara', 'Ms', 'female', null, 'Admissions Officer', 'administrative', 'BA Communication', 850, 'admissions-officer'],
        ['sjohnson', 'Saah', 'Johnson', 'Dr', 'male', 'NURS', 'Medical Officer', 'support', 'MBChB', 1800, 'medical-officer'],
        ['tnagbe', 'Tarnue', 'Nagbe', 'Mr', 'male', 'IT', 'ICT Support Officer', 'technical', 'BSc Information Technology', 800, 'support-desk'],
    ];

    $staffIdByUsername = [];
    foreach ($staffSeed as $i => $row) {
        [$username, $first, $last, $title, $gender, $dept, $designation, $category, $qualification, $salary, $role] = $row;
        $userInsert->execute([
            $uuid(), $username, $username . '@bestbrain.edu.lr', $hash('Staff@2026'),
            $title, $first, $last, $gender, date('Y-m-d', strtotime('-' . (30 + $i) . ' years')),
            '+23177' . str_pad((string) (1000000 + $i * 137), 7, '0', STR_PAD_LEFT),
            $category === 'academic' ? 'lecturer' : 'staff',
        ]);
        $userId = (int) $pdo->lastInsertId();

        $staffInsert->execute([
            $userId,
            sprintf('BBU/STF/%04d/2020', $i + 1),
            $dept === null ? null : $departmentIds[$dept],
            $designation,
            $category,
            'permanent',
            $qualification,
            $designation,
            date('Y-m-d', strtotime('-' . (2 + $i) . ' years')),
            $salary,
            (string) (20000000 + $i * 7919),
        ]);
        $staffIdByUsername[$username] = (int) $pdo->lastInsertId();

        if (isset($roleIds[$role])) {
            $assignRole->execute([$userId, $roleIds[$role]]);
        }
    }
    echo "  " . count($staffSeed) . " staff accounts.\n";

    /* Heads of department and deans */
    $pdo->exec("UPDATE departments SET hod_id = (SELECT user_id FROM staff WHERE staff_number = 'BBU/STF/0004/2020') WHERE code = 'IT'");
    $pdo->exec("UPDATE faculties SET dean_id = (SELECT user_id FROM staff WHERE staff_number = 'BBU/STF/0004/2020') WHERE code = 'CIT'");

    /* ---------------- Students ---------------- */
    $studentInsert = $pdo->prepare(
        'INSERT INTO students (user_id, admission_number, registration_number, program_id, intake_id, campus_id,
                               year_of_study, current_semester, study_mode, admission_date, sponsor_type,
                               nationality, national_id, county, physical_address,
                               emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                               previous_school, previous_qualification, previous_grade, status)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?, "full_time", ?, ?, "Liberian", ?, ?, ?, ?, ?, "parent", ?, "WASSCE", ?, "active")'
    );

    $firstNames = ['Musu', 'Emmanuel', 'Korto', 'Alfred', 'Massa', 'Prince', 'Kula', 'Varney', 'Yatta', 'Momo',
                   'Bendu', 'Sekou', 'Hawa', 'Augustine', 'Satta', 'Boakai', 'Kebbeh', 'Tarnue', 'Deddeh', 'Flomo',
                   'Miatta', 'Saah', 'Nyema', 'Konah', 'Garmai', 'Weah', 'Fatu', 'Sando', 'Wologosi', 'Zoe'];
    $lastNames  = ['Johnson', 'Kollie', 'Gbessay', 'Wesseh', 'Tubman', 'Doe', 'Sirleaf', 'Kpehe', 'Nyanti', 'Toe',
                   'Kamara', 'Freeman', 'Dahn', 'Gbaya', 'Massaquoi', 'Gaye', 'Brownell', 'Nagbe', 'Zeon', 'Cooper',
                   'Wolobah', 'Sumo', 'Kieh', 'Barclay', 'Yancy', 'Karnga', 'Sherman', 'Togba', 'Dolo', 'Kpoto'];
    $counties   = ['Montserrado', 'Nimba', 'Bong', 'Lofa', 'Grand Bassa', 'Margibi', 'Grand Gedeh', 'Maryland', 'Sinoe', 'Bomi'];
    $grades     = ['A1', 'B2', 'B3', 'C4', 'C5', 'C6'];
    $sponsors   = ['self', 'parent', 'government', 'scholarship'];

    $programRows = $pdo->query(
        "SELECT p.id, p.code FROM programs p
          WHERE p.status = 'active' AND EXISTS (SELECT 1 FROM program_courses pc WHERE pc.program_id = p.id)
          ORDER BY p.code"
    )->fetchAll(PDO::FETCH_ASSOC);
    $studentRole = $roleIds['student'] ?? null;

    $studentIds = [];
    $created    = 0;
    for ($i = 0; $i < 60; $i++) {
        $first   = $firstNames[$i % count($firstNames)];
        $last    = $lastNames[($i * 7) % count($lastNames)];
        $program = $programRows[$i % count($programRows)];
        $year    = ($i % 4) + 1;
        $entry   = 2026 - $year;

        $username = strtolower(substr($first, 0, 1) . $last);
        $suffix   = 0;
        while ((int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = " . $pdo->quote($username))->fetchColumn() > 0) {
            $username = strtolower(substr($first, 0, 1) . $last) . (++$suffix);
        }

        $userInsert->execute([
            $uuid(), $username, $username . '@students.bestbrain.edu.lr', $hash('Student@2026'),
            null, $first, $last, $i % 2 === 0 ? 'female' : 'male',
            date('Y-m-d', strtotime('-' . (18 + ($i % 6)) . ' years')),
            '+23177' . str_pad((string) (2000000 + $i * 971), 7, '0', STR_PAD_LEFT),
            'student',
        ]);
        $userId = (int) $pdo->lastInsertId();

        $admission = sprintf('BBU/%s/%04d/%d', $program['code'], $i + 1, $entry);
        $studentInsert->execute([
            $userId,
            $admission,
            $admission,
            $program['id'],
            $year === 1 ? 2 : 1,
            $year,
            2,
            $entry . '-09-01',
            $sponsors[$i % count($sponsors)],
            (string) (30000000 + $i * 3301),
            $counties[$i % count($counties)],
            $counties[$i % count($counties)] . ', Liberia',
            $firstNames[($i * 3) % count($firstNames)] . ' ' . $last,
            '+23177' . str_pad((string) (3000000 + $i * 811), 7, '0', STR_PAD_LEFT),
            $lastNames[($i * 5) % count($lastNames)] . ' High School',
            $grades[$i % count($grades)],
        ]);
        $studentIds[] = (int) $pdo->lastInsertId();

        if ($studentRole !== null) {
            $assignRole->execute([$userId, $studentRole]);
        }
        $created++;
    }
    echo "  {$created} students enrolled across the programmes.\n";

    /* ---------------- Class offerings for the current semester ---------------- */
    $currentSemester = (int) $pdo->query('SELECT id FROM semesters WHERE is_current = 1 LIMIT 1')->fetchColumn();
    $previousSemester = (int) $pdo->query('SELECT id FROM semesters WHERE id = ' . max(1, $currentSemester - 1))->fetchColumn();

    $lecturers = array_values($pdo->query(
        "SELECT s.id FROM staff s WHERE s.staff_category = 'academic'"
    )->fetchAll(PDO::FETCH_COLUMN));
    $roomIds = array_values($pdo->query("SELECT id FROM rooms WHERE room_type IN ('lecture_hall','laboratory')")->fetchAll(PDO::FETCH_COLUMN));

    $offeringInsert = $pdo->prepare(
        'INSERT INTO course_offerings (course_id, semester_id, program_id, section, lecturer_id, room_id,
                                       capacity, delivery_mode, coursework_weight, exam_weight, status)
         VALUES (?, ?, NULL, "A", ?, ?, ?, "physical", 30, 70, ?)'
    );

    $offerings = [];
    foreach ([$previousSemester, $currentSemester] as $index => $semesterId) {
        if ($semesterId === 0) {
            continue;
        }
        $isCurrent = $semesterId === $currentSemester;
        $plannedCourses = $pdo->prepare(
            'SELECT DISTINCT pc.course_id AS id
               FROM program_courses pc
               JOIN students st ON st.program_id = pc.program_id AND st.year_of_study = pc.year_of_study
               JOIN semesters sem ON sem.id = ? AND sem.semester_number = pc.semester_number
              WHERE pc.course_id IS NOT NULL
              ORDER BY pc.course_id'
        );
        $plannedCourses->execute([$semesterId]);
        $n = 0;
        foreach ($plannedCourses->fetchAll(PDO::FETCH_ASSOC) as $course) {
            $n++;
            $offeringInsert->execute([
                $course['id'],
                $semesterId,
                $lecturers[$n % max(1, count($lecturers))],
                $roomIds[$n % max(1, count($roomIds))],
                60,
                $isCurrent ? 'open' : 'completed',
            ]);
            $offeringId = (int) $pdo->lastInsertId();
            $offerings[$semesterId][$course['id']] = $offeringId;

            if ($isCurrent) {
                $pdo->prepare(
                    'INSERT IGNORE INTO timetable_slots (offering_id, semester_id, day_of_week, start_time, end_time, room_id, session_type)
                     VALUES (?, ?, ?, ?, ?, ?, "lecture")'
                )->execute([
                    $offeringId,
                    $semesterId,
                    ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'][$n % 5],
                    sprintf('%02d:00:00', 8 + ($n % 8)),
                    sprintf('%02d:00:00', 10 + ($n % 8)),
                    $roomIds[$n % max(1, count($roomIds))],
                ]);
            }
        }
    }
    echo "  Class offerings and timetable slots created.\n";

    /* ---------------- Registrations and results ---------------- */
    $registrationInsert = $pdo->prepare(
        'INSERT IGNORE INTO course_registrations (student_id, offering_id, semester_id, registration_type,
                                                  approval_status, status, approved_at)
         VALUES (?, ?, ?, "normal", ?, "registered", ?)'
    );
    $resultInsert = $pdo->prepare(
        'INSERT IGNORE INTO course_results (student_id, offering_id, semester_id, course_id, coursework_score,
                                            exam_score, total_score, grade, grade_point, credit_hours,
                                            quality_points, attempt, outcome, is_published, published_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1, NOW())'
    );

    $gradeFor = static function (float $score): array {
        return match (true) {
            $score >= 90 => ['A', 4.0, 'pass'],
            $score >= 80 => ['B', 3.0, 'pass'],
            $score >= 70 => ['C', 2.0, 'pass'],
            $score >= 60 => ['D', 1.0, 'pass'],
            default      => ['F', 0.0, 'fail'],
        };
    };

    $studentPlan = $pdo->query(
        'SELECT s.id, s.program_id, s.year_of_study, s.current_semester FROM students s'
    )->fetchAll(PDO::FETCH_ASSOC);

    $registrations = 0;
    $results = 0;
    // One row per term in the student's year; of an either/or pair, the first alternative.
    $planned = $pdo->prepare(
        'SELECT pc.course_id, pc.semester_number, c.credit_hours
           FROM program_courses pc JOIN courses c ON c.id = pc.course_id
          WHERE pc.program_id = ? AND pc.year_of_study = ?
            AND (pc.choice_group IS NULL OR pc.sort_order = (
                 SELECT MIN(x.sort_order) FROM program_courses x
                  WHERE x.program_id = pc.program_id AND x.year_of_study = pc.year_of_study
                    AND x.semester_number = pc.semester_number AND x.choice_group = pc.choice_group))
          ORDER BY pc.semester_number, pc.sort_order'
    );
    $semesterNumber = $pdo->query('SELECT id, semester_number FROM semesters')->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($studentPlan as $student) {
        $planned->execute([$student['program_id'], $student['year_of_study']]);

        foreach ($planned->fetchAll(PDO::FETCH_ASSOC) as $index => $course) {
            $courseSemester = (int) $course['semester_number'];

            // Previous semester -> completed with a published result.
            if ($previousSemester > 0 && $courseSemester === (int) $semesterNumber[$previousSemester]
                && isset($offerings[$previousSemester][$course['course_id']])) {
                $offeringId = $offerings[$previousSemester][$course['course_id']];
                $registrationInsert->execute([$student['id'], $offeringId, $previousSemester, 'approved', date('Y-m-d H:i:s')]);
                $registrations++;

                $coursework = random_int(55, 98);
                $exam       = random_int(50, 98);
                $total      = round(($coursework * 0.3) + ($exam * 0.7), 2);
                [$grade, $point, $outcome] = $gradeFor($total);
                $resultInsert->execute([
                    $student['id'], $offeringId, $previousSemester, $course['course_id'],
                    $coursework, $exam, $total, $grade, $point, $course['credit_hours'],
                    round($point * (int) $course['credit_hours'], 2), $outcome,
                ]);
                $results++;
            }

            // Current semester -> live registrations awaiting or holding approval.
            if ($courseSemester === (int) $semesterNumber[$currentSemester]
                && isset($offerings[$currentSemester][$course['course_id']])) {
                $offeringId = $offerings[$currentSemester][$course['course_id']];
                $approved   = $index % 4 !== 0;
                $registrationInsert->execute([
                    $student['id'], $offeringId, $currentSemester,
                    $approved ? 'approved' : 'pending',
                    $approved ? date('Y-m-d H:i:s') : null,
                ]);
                $registrations++;
            }
        }
    }
    $pdo->exec(
        'UPDATE course_offerings o SET enrolled_count =
            (SELECT COUNT(*) FROM course_registrations cr WHERE cr.offering_id = o.id AND cr.status = "registered")'
    );
    echo "  {$registrations} course registrations, {$results} published results.\n";

    /* Semester GPA rows derived from the published results. */
    $pdo->exec(
        'INSERT IGNORE INTO semester_results
            (student_id, semester_id, year_of_study, credits_registered, credits_earned, quality_points, gpa, cgpa, classification, decision)
         SELECT r.student_id, r.semester_id, s.year_of_study,
                SUM(r.credit_hours),
                SUM(CASE WHEN r.outcome = "pass" THEN r.credit_hours ELSE 0 END),
                SUM(r.quality_points),
                ROUND(SUM(r.quality_points) / NULLIF(SUM(r.credit_hours), 0), 2),
                ROUND(SUM(r.quality_points) / NULLIF(SUM(r.credit_hours), 0), 2),
                "Pending classification",
                "proceed"
           FROM course_results r
           JOIN students s ON s.id = r.student_id
          WHERE r.is_published = 1
          GROUP BY r.student_id, r.semester_id, s.year_of_study'
    );
    $pdo->exec(
        'UPDATE students st SET
            cgpa = COALESCE((SELECT ROUND(SUM(quality_points) / NULLIF(SUM(credit_hours), 0), 2)
                               FROM course_results WHERE student_id = st.id AND is_published = 1), 0),
            credits_earned = COALESCE((SELECT SUM(credit_hours) FROM course_results
                                        WHERE student_id = st.id AND is_published = 1 AND outcome = "pass"), 0)'
    );

    /* ---------------- Sample student bills: by status, department and college ---------------- */
    // The billing service picks the most specific active structure for each student:
    // programme > department > college > all colleges, then status-specific over "all".
    require_once dirname(__DIR__, 2) . '/app/Core/Model.php';
    require_once dirname(__DIR__, 2) . '/app/Models/FeeStructure.php';

    $feeTypeIds = $pdo->query('SELECT code, id FROM fee_types')->fetchAll(PDO::FETCH_KEY_PAIR);
    $feeTypeCategory = $pdo->query('SELECT code, category FROM fee_types')->fetchAll(PDO::FETCH_KEY_PAIR);
    $facultyIds    = $pdo->query('SELECT code, id FROM faculties')->fetchAll(PDO::FETCH_KEY_PAIR);
    $departmentIds = $pdo->query('SELECT code, id FROM departments')->fetchAll(PDO::FETCH_KEY_PAIR);
    $programIdsByCode = $pdo->query('SELECT code, id FROM programs')->fetchAll(PDO::FETCH_KEY_PAIR);

    $structureInsert = $pdo->prepare(
        'INSERT INTO fee_structures (faculty_id, department_id, program_id, academic_year_id, student_status, name, total_amount, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "active")'
    );
    $structureItemInsert = $pdo->prepare(
        'INSERT INTO fee_structure_items (fee_structure_id, fee_type_id, amount, is_mandatory) VALUES (?, ?, ?, 1)'
    );
    $installmentInsert = $pdo->prepare(
        'INSERT INTO fee_structure_installments (fee_structure_id, installment_number, label, tuition_percent, due_days)
         VALUES (?, ?, ?, ?, ?)'
    );

    $newStudentFees  = ['ADM' => 50, 'ORI' => 25, 'IDC' => 10, 'CAU' => 30];
    $standardPlan    = [['1st Installment', 50, 0], ['2nd Installment', 25, 42], ['3rd Installment', 25, 84]];
    $graduatePlan    = [['1st Installment', 40, 0], ['2nd Installment', 30, 42], ['3rd Installment', 30, 84]];

    // [name, scope type, scope code, student status, fees, plan]
    $sampleBills = [
        ['New Student Bill - All Colleges', null, null, 'new',
            ['TUI' => 750, 'REG' => 40, 'EXM' => 60, 'LIB' => 30, 'MED' => 45, 'ACT' => 25] + $newStudentFees, $standardPlan],
        ['Continuing Student Bill - All Colleges', null, null, 'continuing',
            ['TUI' => 750, 'REG' => 40, 'EXM' => 60, 'LIB' => 30, 'MED' => 45, 'ACT' => 25], $standardPlan],
        ['College of Information Technology - Continuing Students', 'faculty', 'CIT', 'continuing',
            ['TUI' => 800, 'REG' => 40, 'EXM' => 60, 'LIB' => 30, 'ICT' => 75, 'LAB' => 40, 'MED' => 45, 'ACT' => 25], $standardPlan],
        ['College of Health Science - New Students', 'faculty', 'CHS', 'new',
            ['TUI' => 950, 'REG' => 40, 'EXM' => 60, 'LIB' => 30, 'LAB' => 60, 'MED' => 45, 'ACT' => 25] + $newStudentFees, $standardPlan],
        ['College of Health Science - Continuing Students', 'faculty', 'CHS', 'continuing',
            ['TUI' => 950, 'REG' => 40, 'EXM' => 60, 'LIB' => 30, 'LAB' => 60, 'MED' => 45, 'ACT' => 25], $standardPlan],
        ['Department of Nursing - Continuing Students', 'department', 'NURS', 'continuing',
            ['TUI' => 950, 'REG' => 40, 'EXM' => 60, 'LIB' => 30, 'LAB' => 60, 'CLN' => 120, 'MED' => 45, 'ACT' => 25], $standardPlan],
        ['Department of Accounting - Continuing Students', 'department', 'ACCT', 'continuing',
            ['TUI' => 700, 'REG' => 40, 'EXM' => 60, 'LIB' => 30, 'ICT' => 30, 'MED' => 45, 'ACT' => 25], $standardPlan],
        ['BSc. Nursing (Nurse-Midwifery) - All Students', 'program', 'BSNMW', 'all',
            ['TUI' => 1100, 'REG' => 40, 'EXM' => 75, 'LIB' => 30, 'LAB' => 60, 'CLN' => 150, 'MED' => 45], $graduatePlan],
    ];

    $plans = [];
    foreach ($sampleBills as [$name, $scopeType, $scopeCode, $studentStatus, $fees, $plan]) {
        $structureInsert->execute([
            $scopeType === 'faculty' ? $facultyIds[$scopeCode] : null,
            $scopeType === 'department' ? $departmentIds[$scopeCode] : null,
            $scopeType === 'program' ? $programIdsByCode[$scopeCode] : null,
            $yearIds['2026'],
            $studentStatus,
            $name,
            array_sum($fees),
        ]);
        $structureId = (int) $pdo->lastInsertId();
        foreach ($fees as $code => $amount) {
            $structureItemInsert->execute([$structureId, $feeTypeIds[$code], $amount]);
        }
        foreach ($plan as $n => [$label, $percent, $dueDays]) {
            $installmentInsert->execute([$structureId, $n + 1, $label, $percent, $dueDays]);
            $plans[$structureId][] = ['installment_number' => $n + 1, 'label' => $label, 'tuition_percent' => $percent, 'due_days' => $dueDays];
        }
    }
    echo '  ' . count($sampleBills) . " sample fee structures (by status, department, college and programme).\n";

    $resolveStructure = $pdo->prepare(
        'SELECT * FROM fee_structures
          WHERE academic_year_id = ? AND status = "active"
            AND (program_id IS NULL OR program_id = ?)
            AND (department_id IS NULL OR department_id = ?)
            AND (faculty_id IS NULL OR faculty_id = ?)
            AND student_status IN ("all", ?)
          ORDER BY program_id IS NOT NULL DESC, department_id IS NOT NULL DESC, faculty_id IS NOT NULL DESC,
                   student_status != "all" DESC, id DESC
          LIMIT 1'
    );
    $structureItems = $pdo->prepare(
        'SELECT i.fee_type_id, i.amount, t.name, t.category FROM fee_structure_items i
           JOIN fee_types t ON t.id = i.fee_type_id WHERE i.fee_structure_id = ?'
    );
    $invoiceInsert = $pdo->prepare(
        'INSERT INTO invoices (invoice_number, student_id, semester_id, fee_structure_id, title, total_amount,
                               discount_amount, amount_paid, balance, issue_date, due_date, status)
         VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)'
    );
    $invoiceItemInsert = $pdo->prepare(
        'INSERT INTO invoice_items (invoice_id, fee_type_id, description, quantity, unit_amount, amount)
         VALUES (?, ?, ?, 1, ?, ?)'
    );
    $invoiceInstallmentInsert = $pdo->prepare(
        'INSERT INTO invoice_installments (invoice_id, installment_number, label, amount, amount_paid, due_date, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $paymentInsert = $pdo->prepare(
        'INSERT INTO payments (receipt_number, student_id, invoice_id, amount, method, reference, paid_at, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "confirmed")'
    );
    $enrollmentInsert = $pdo->prepare(
        'INSERT INTO semester_enrollments (student_id, semester_id, invoice_id, status, enrolled_at) VALUES (?, ?, ?, ?, ?)'
    );

    $semesterStart = (string) $pdo->query('SELECT start_date FROM semesters WHERE id = ' . $currentSemester)->fetchColumn();
    $studentScopes = $pdo->query(
        'SELECT s.id, s.program_id, s.year_of_study, p.department_id, d.faculty_id
           FROM students s JOIN programs p ON p.id = s.program_id JOIN departments d ON d.id = p.department_id'
    )->fetchAll(PDO::FETCH_ASSOC);

    $invoiceCount  = 0;
    $paymentCount  = 0;
    $enrolledCount = 0;
    foreach ($studentScopes as $index => $student) {
        $resolveStructure->execute([
            $yearIds['2026'], $student['program_id'], $student['department_id'], $student['faculty_id'],
            (int) $student['year_of_study'] === 1 ? 'new' : 'continuing',
        ]);
        $feeStructure = $resolveStructure->fetch(PDO::FETCH_ASSOC);
        if ($feeStructure === false) {
            continue;
        }
        $structureItems->execute([$feeStructure['id']]);
        $items = $structureItems->fetchAll(PDO::FETCH_ASSOC);

        $total   = 0.0;
        $tuition = 0.0;
        foreach ($items as $item) {
            $total += (float) $item['amount'];
            if ($item['category'] === 'tuition') {
                $tuition += (float) $item['amount'];
            }
        }
        $installments = App\Models\FeeStructure::splitIntoInstallments($plans[$feeStructure['id']], $tuition, $total - $tuition, 0.0);
        $firstInstallment = $installments[0]['amount'];

        // Payment patterns: paid in full, exactly the 1st installment, part of the 1st, nothing.
        $paid = match ($index % 4) {
            0       => $total,
            1       => $firstInstallment,
            2       => round($firstInstallment * 0.4, 2),
            default => 0.0,
        };
        $balance = round($total - $paid, 2);
        $status  = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

        $invoiceCount++;
        $invoiceInsert->execute([
            sprintf('INV-%s-%05d', date('Ym'), $invoiceCount),
            $student['id'],
            $currentSemester,
            $feeStructure['id'],
            $feeStructure['name'],
            $total,
            $paid,
            $balance,
            date('Y-m-d', strtotime($semesterStart . ' -14 days')),
            date('Y-m-d', strtotime($semesterStart . ' ' . sprintf('%+d', end($installments)['due_days']) . ' days')),
            $status,
        ]);
        $invoiceId = (int) $pdo->lastInsertId();

        foreach ($items as $item) {
            $invoiceItemInsert->execute([$invoiceId, $item['fee_type_id'], $item['name'], $item['amount'], $item['amount']]);
        }

        $remaining = $paid;
        foreach ($installments as $installment) {
            $applied   = round(min($installment['amount'], $remaining), 2);
            $remaining = round($remaining - $applied, 2);
            $dueDate   = date('Y-m-d', strtotime($semesterStart . ' ' . sprintf('%+d', $installment['due_days']) . ' days'));
            $invoiceInstallmentInsert->execute([
                $invoiceId,
                $installment['installment_number'],
                $installment['label'],
                $installment['amount'],
                $applied,
                $dueDate,
                match (true) {
                    $applied >= $installment['amount'] - 0.005 => 'paid',
                    $applied > 0                               => 'partial',
                    $dueDate < date('Y-m-d')                   => 'overdue',
                    default                                    => 'unpaid',
                },
            ]);
        }

        if ($paid > 0) {
            $paymentCount++;
            $paymentInsert->execute([
                sprintf('RCP-%s-%05d', date('Ym'), $paymentCount),
                $student['id'],
                $invoiceId,
                $paid,
                ['mpesa', 'bank_transfer', 'cash', 'cheque'][$index % 4],
                'REF' . strtoupper(substr(md5((string) $index), 0, 8)),
                date('Y-m-d H:i:s', strtotime($semesterStart . ' -' . random_int(1, 10) . ' days')),
            ]);
        }

        // 1st installment cleared -> enrolled for the semester, registration complete.
        $enrolled = $paid >= $firstInstallment - 0.005;
        $enrollmentInsert->execute([
            $student['id'], $currentSemester, $invoiceId,
            $enrolled ? 'enrolled' : 'pending_payment',
            $enrolled ? date('Y-m-d H:i:s', strtotime($semesterStart . ' -1 day')) : null,
        ]);
        $enrolledCount += $enrolled ? 1 : 0;
    }

    // Course registration requires enrolment, so drop the current-semester
    // registrations seeded earlier for students who have not cleared the 1st installment.
    $pdo->exec(
        'DELETE cr FROM course_registrations cr
           JOIN semester_enrollments e ON e.student_id = cr.student_id AND e.semester_id = cr.semester_id
          WHERE cr.semester_id = ' . $currentSemester . ' AND e.status != "enrolled"'
    );
    $pdo->exec(
        'UPDATE course_offerings o SET enrolled_count =
            (SELECT COUNT(*) FROM course_registrations cr WHERE cr.offering_id = o.id AND cr.status = "registered")'
    );
    echo "  {$invoiceCount} invoices with installments, {$paymentCount} payments, {$enrolledCount} students enrolled (registration complete).\n";

    /* ---------------- Library ---------------- */
    $books = [
        ['9780262033848', 'Introduction to Algorithms', 'Cormen, Leiserson, Rivest, Stein', 'MIT Press', '4th', 2022, 9, 12, 95],
        ['9780134685991', 'Effective Java', 'Joshua Bloch', 'Addison-Wesley', '3rd', 2018, 9, 8, 60],
        ['9780132350884', 'Clean Code', 'Robert C. Martin', 'Prentice Hall', '1st', 2008, 9, 10, 45],
        ['9780133594140', 'Computer Networking: A Top-Down Approach', 'Kurose and Ross', 'Pearson', '8th', 2021, 9, 6, 85],
        ['9781118063330', 'Operating System Concepts', 'Silberschatz, Galvin, Gagne', 'Wiley', '10th', 2018, 9, 7, 90],
        ['9780073523323', 'Database System Concepts', 'Silberschatz, Korth, Sudarshan', 'McGraw-Hill', '7th', 2019, 9, 9, 92],
        ['9781292024820', 'Financial Accounting', 'Weygandt and Kimmel', 'Wiley', '10th', 2019, 4, 11, 78],
        ['9780078112720', 'Principles of Management', 'Griffin', 'Cengage', '12th', 2017, 4, 8, 62],
        ['9780321749086', 'University Physics', 'Young and Freedman', 'Pearson', '14th', 2016, 2, 6, 98],
        ['9781259253157', 'Calculus: Early Transcendentals', 'James Stewart', 'Cengage', '8th', 2015, 2, 10, 105],
        ['9780323673204', 'Fundamentals of Nursing', 'Potter and Perry', 'Elsevier', '10th', 2021, 6, 7, 120],
        ['9780199234899', 'A Dictionary of Education', 'Susan Wallace', 'Oxford', '2nd', 2015, 8, 4, 40],
    ];
    $bookInsert = $pdo->prepare(
        'INSERT INTO books (accession_number, isbn, title, author, publisher, edition, publication_year,
                            category_id, shelf_location, total_copies, available_copies, price, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "available")'
    );
    foreach ($books as $i => [$isbn, $title, $author, $publisher, $edition, $year, $category, $copies, $price]) {
        $bookInsert->execute([
            sprintf('ACC-%06d', $i + 1), $isbn, $title, $author, $publisher, $edition, $year,
            $category, 'Shelf ' . chr(65 + ($i % 6)) . '-' . (($i % 9) + 1), $copies, $copies, $price,
        ]);
    }

    /* A handful of active loans, one of them overdue. */
    $borrowers = $pdo->query(
        'SELECT u.id FROM users u JOIN students s ON s.user_id = u.id ORDER BY u.id LIMIT 8'
    )->fetchAll(PDO::FETCH_COLUMN);
    $loanInsert = $pdo->prepare(
        'INSERT INTO book_loans (book_id, user_id, issued_at, due_date, status) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($borrowers as $i => $borrowerId) {
        $overdue = $i % 4 === 0;
        $issued  = strtotime('-' . ($overdue ? 30 : random_int(2, 10)) . ' days');
        $loanInsert->execute([
            ($i % count($books)) + 1,
            $borrowerId,
            date('Y-m-d H:i:s', $issued),
            date('Y-m-d', strtotime('+14 days', $issued)),
            $overdue ? 'overdue' : 'borrowed',
        ]);
    }
    $pdo->exec(
        'UPDATE books b SET available_copies = GREATEST(b.total_copies -
            (SELECT COUNT(*) FROM book_loans l WHERE l.book_id = b.id AND l.status IN ("borrowed","overdue")), 0)'
    );
    echo "  " . count($books) . " library titles with active loans.\n";

    /* ---------------- Hostels ---------------- */
    $pdo->exec(
        "INSERT INTO hostels (campus_id, code, name, gender, total_rooms, location, status) VALUES
           (1, 'HST-A', 'Ducor Hall', 'male', 40, 'North wing', 'active'),
           (1, 'HST-B', 'Providence Hall', 'female', 40, 'South wing', 'active'),
           (1, 'HST-C', 'Nimba Hall', 'mixed', 30, 'East wing', 'active')"
    );
    $hostelRoomInsert = $pdo->prepare(
        'INSERT INTO hostel_rooms (hostel_id, room_number, floor, room_type, capacity, occupied, fee_per_semester, status)
         VALUES (?, ?, ?, ?, ?, 0, ?, "available")'
    );
    for ($hostel = 1; $hostel <= 3; $hostel++) {
        for ($room = 1; $room <= 20; $room++) {
            $hostelRoomInsert->execute([
                $hostel,
                sprintf('%s%02d', chr(64 + $hostel), $room),
                (int) ceil($room / 10),
                $room % 5 === 0 ? 'single' : 'double',
                $room % 5 === 0 ? 1 : 2,
                $room % 5 === 0 ? 280 : 180,
            ]);
        }
    }
    $allocationInsert = $pdo->prepare(
        'INSERT IGNORE INTO hostel_allocations (student_id, hostel_room_id, semester_id, bed_number, status)
         VALUES (?, ?, ?, ?, "checked_in")'
    );
    foreach (array_slice($studentIds, 0, 24) as $i => $studentId) {
        $allocationInsert->execute([$studentId, $i + 1, $currentSemester, 'B' . (($i % 2) + 1)]);
    }
    $pdo->exec(
        'UPDATE hostel_rooms r SET occupied =
            (SELECT COUNT(*) FROM hostel_allocations a WHERE a.hostel_room_id = r.id AND a.status IN ("allocated","checked_in"))'
    );
    $pdo->exec("UPDATE hostel_rooms SET status = 'full' WHERE occupied >= capacity");
    echo "  3 halls of residence with 60 rooms and current allocations.\n";

    /* ---------------- Admissions pipeline ---------------- */
    $applicationInsert = $pdo->prepare(
        'INSERT INTO applications (application_number, intake_id, program_id, first_name, last_name, email, phone,
                                   gender, date_of_birth, nationality, national_id, county, previous_school,
                                   qualification, grade_obtained, year_completed, study_mode, sponsor_type,
                                   application_fee_paid, score, status, submitted_at)
         VALUES (?, 3, ?, ?, ?, ?, ?, ?, ?, "Liberian", ?, ?, ?, "WASSCE", ?, 2025, "full_time", ?, 1, ?, ?, ?)'
    );
    $applicationStatuses = ['submitted', 'submitted', 'under_review', 'shortlisted', 'accepted', 'rejected', 'submitted', 'accepted'];
    for ($i = 0; $i < 24; $i++) {
        $first  = $firstNames[($i * 5) % count($firstNames)];
        $last   = $lastNames[($i * 3) % count($lastNames)];
        $status = $applicationStatuses[$i % count($applicationStatuses)];
        $applicationInsert->execute([
            sprintf('APP-2026-%05d', $i + 1),
            $programRows[$i % count($programRows)]['id'],
            $first,
            $last,
            strtolower($first . '.' . $last . $i) . '@example.com',
            '+23177' . str_pad((string) (4000000 + $i * 613), 7, '0', STR_PAD_LEFT),
            $i % 2 === 0 ? 'male' : 'female',
            date('Y-m-d', strtotime('-' . (18 + ($i % 4)) . ' years')),
            (string) (40000000 + $i * 1237),
            $counties[$i % count($counties)],
            $lastNames[($i * 2) % count($lastNames)] . ' High School',
            $grades[$i % count($grades)],
            $sponsors[$i % count($sponsors)],
            random_int(55, 92),
            $status,
            date('Y-m-d H:i:s', strtotime('-' . random_int(1, 60) . ' days')),
        ]);
    }
    echo "  24 admission applications across the pipeline.\n";

    /* ---------------- Communication ---------------- */
    $adminUserId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
    $announcementInsert = $pdo->prepare(
        'INSERT INTO announcements (title, body, audience, priority, published_at, expires_at, created_by, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, "published")'
    );
    $announcements = [
        ['Semester Two registration is now open', 'All continuing students should register for their units through the student portal before the registration deadline. Registration closes at midnight on the published date; late registration attracts a penalty.', 'students', 'high', '-6 days', '+20 days'],
        ['Examination timetable published', 'The end of semester examination timetable is available on the portal. Candidates must carry their student identity cards to every examination session.', 'students', 'urgent', '-3 days', '+45 days'],
        ['Staff development workshop', 'A workshop on outcome-based curriculum delivery will be held in the Business Auditorium. All teaching staff are expected to attend.', 'staff', 'normal', '-2 days', '+14 days'],
        ['Library extended opening hours', 'The main library will remain open until 22:00 on weekdays throughout the examination period.', 'all', 'normal', '-1 day', '+30 days'],
        ['Fee payment reminder', 'The 1st installment of the semester bill is compulsory. Once it is paid in full you are enrolled for the semester and your registration is complete. Later installments fall due on the dates shown on your invoice.', 'students', 'high', '-4 days', '+25 days'],
    ];
    foreach ($announcements as [$title, $body, $audience, $priority, $published, $expires]) {
        $announcementInsert->execute([
            $title, $body, $audience, $priority,
            date('Y-m-d H:i:s', strtotime($published)),
            date('Y-m-d H:i:s', strtotime($expires)),
            $adminUserId,
        ]);
    }

    $eventInsert = $pdo->prepare(
        'INSERT INTO events (title, description, event_type, start_datetime, end_datetime, venue, organizer, is_public, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)'
    );
    $events = [
        ['Semester Two Opening Ceremony', 'Official opening of the semester by the Vice Chancellor.', 'academic', '+3 days', '+3 days', 'Main Auditorium', 'Office of the VC'],
        ['Inter-faculty Sports Day', 'Annual sporting competition between faculties.', 'sports', '+12 days', '+12 days', 'University Grounds', 'Sports Department'],
        ['Career Fair 2026', 'Meet employers and explore internship opportunities.', 'other', '+20 days', '+21 days', 'Business Tower Foyer', 'Career Services'],
        ['Research Week', 'Presentation of postgraduate research findings.', 'academic', '+35 days', '+39 days', 'Science Complex', 'Directorate of Research'],
        ['Graduation Ceremony', '24th graduation ceremony.', 'graduation', '+90 days', '+90 days', 'University Grounds', 'Registrar Academic'],
    ];
    foreach ($events as [$title, $description, $type, $start, $end, $venue, $organizer]) {
        $eventInsert->execute([
            $title, $description, $type,
            date('Y-m-d 09:00:00', strtotime($start)),
            date('Y-m-d 16:00:00', strtotime($end)),
            $venue, $organizer, $adminUserId,
        ]);
    }
    echo "  Announcements and calendar events.\n";

    /* ---------------- HR, support desk and student services ---------------- */
    $leaveInsert = $pdo->prepare(
        'INSERT INTO leave_requests (staff_id, leave_type_id, start_date, end_date, days, reason, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $staffIdList = array_values($staffIdByUsername);
    $leaveReasons = [
        'Annual leave to attend to family matters.',
        'Medical treatment as advised by the university clinic.',
        'Attending a professional certification course.',
        'Bereavement in the immediate family.',
    ];
    foreach (array_slice($staffIdList, 0, 6) as $i => $staffId) {
        $start = strtotime('+' . (($i + 1) * 5) . ' days');
        $leaveInsert->execute([
            $staffId,
            ($i % 4) + 1,
            date('Y-m-d', $start),
            date('Y-m-d', strtotime('+7 days', $start)),
            7,
            $leaveReasons[$i % count($leaveReasons)],
            ['pending', 'approved', 'pending', 'rejected'][$i % 4],
        ]);
    }

    $ticketInsert = $pdo->prepare(
        'INSERT INTO support_tickets (ticket_number, user_id, category, subject, body, priority, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $tickets = [
        ['ict', 'Cannot access the student portal', 'I keep getting an error when I try to open my results page.', 'high', 'open'],
        ['finance', 'Payment not reflected', 'I paid my fees three days ago but the balance has not changed.', 'urgent', 'in_progress'],
        ['academic', 'Unit registration blocked', 'The system says I have not met the prerequisite for CS 201.', 'normal', 'open'],
        ['hostel', 'Room maintenance request', 'The window latch in room A12 is broken.', 'low', 'resolved'],
        ['library', 'Book renewal request', 'I would like to renew Introduction to Algorithms for two more weeks.', 'normal', 'open'],
    ];
    $ticketUsers = $pdo->query('SELECT id FROM users WHERE user_type = "student" ORDER BY id LIMIT 5')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tickets as $i => [$category, $subject, $body, $priority, $status]) {
        $ticketInsert->execute([
            sprintf('TKT-%s-%04d', date('Y'), $i + 1),
            $ticketUsers[$i % max(1, count($ticketUsers))],
            $category, $subject, $body, $priority, $status,
        ]);
    }

    /* Clearance rows for finalists. */
    $finalists = $pdo->query('SELECT id FROM students WHERE year_of_study = 4 LIMIT 10')->fetchAll(PDO::FETCH_COLUMN);
    $clearanceInsert = $pdo->prepare(
        'INSERT IGNORE INTO clearances (student_id, clearance_type, unit, status) VALUES (?, "graduation", ?, ?)'
    );
    foreach ($finalists as $i => $studentId) {
        foreach (['finance', 'library', 'hostel', 'department', 'registrar'] as $j => $unit) {
            $clearanceInsert->execute([$studentId, $unit, ($i + $j) % 3 === 0 ? 'pending' : 'cleared']);
        }
    }

    /* A couple of scholarships. */
    $pdo->exec(
        "INSERT INTO scholarships (name, sponsor, award_type, percentage, amount, slots, criteria, status) VALUES
           ('Vice Chancellor Merit Award', 'Best Brain University', 'partial', 50.00, 0, 20, 'Awarded to students with a CGPA of 3.6 and above.', 'active'),
           ('Needy Students Bursary', 'University Endowment Fund', 'fixed_amount', 0, 600, 50, 'Means-tested support for students from low income households.', 'active'),
           ('Sports Excellence Scholarship', 'Liberia National Sports Commission', 'full', 100.00, 0, 5, 'Full tuition waiver for students representing the university nationally.', 'active')"
    );

    /* Attendance for the current semester so the reports have data. */
    $attendanceOfferings = array_slice($offerings[$currentSemester] ?? [], 0, 6, true);
    $sessionInsert = $pdo->prepare(
        'INSERT IGNORE INTO attendance_sessions (offering_id, session_date, start_time, end_time, topic, session_type, status)
         VALUES (?, ?, "08:00:00", "10:00:00", ?, "lecture", "closed")'
    );
    $recordInsert = $pdo->prepare(
        'INSERT IGNORE INTO attendance_records (session_id, student_id, status) VALUES (?, ?, ?)'
    );
    foreach ($attendanceOfferings as $offeringId) {
        for ($week = 1; $week <= 4; $week++) {
            $sessionInsert->execute([
                $offeringId,
                date('Y-m-d', strtotime('-' . (7 * $week) . ' days')),
                'Week ' . $week . ' lecture',
            ]);
            $sessionId = (int) $pdo->lastInsertId();
            if ($sessionId === 0) {
                continue;
            }
            $enrolled = $pdo->prepare(
                'SELECT student_id FROM course_registrations WHERE offering_id = ? AND status = "registered" LIMIT 40'
            );
            $enrolled->execute([$offeringId]);
            foreach ($enrolled->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
                $roll = random_int(1, 10);
                $recordInsert->execute([
                    $sessionId,
                    $studentId,
                    $roll <= 7 ? 'present' : ($roll <= 8 ? 'late' : ($roll <= 9 ? 'absent' : 'excused')),
                ]);
            }
        }
    }
    echo "  HR, support desk, clearance, scholarships and attendance records.\n";
};
