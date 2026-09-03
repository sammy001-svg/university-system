<?php
/**
 * Core seed: permissions, roles, the super administrator, settings and the
 * grading scale. Safe to re-run - everything uses INSERT IGNORE / upserts.
 */
declare(strict_types=1);

return static function (PDO $pdo): void {

    /* ---------------- Permissions ---------------- */
    $modules = [
        'dashboard'     => ['view' => 'View dashboard'],
        'users'         => ['view' => 'View user accounts', 'create' => 'Create users', 'edit' => 'Edit users', 'delete' => 'Delete users', 'reset' => 'Reset passwords'],
        'roles'         => ['view' => 'View roles', 'create' => 'Create roles', 'edit' => 'Edit roles', 'delete' => 'Delete roles', 'assign' => 'Assign permissions'],
        'settings'      => ['manage' => 'Manage system settings'],
        'audit'         => ['view' => 'View the audit trail'],
        'academics'     => ['view' => 'View academic structure', 'create' => 'Create academic records', 'edit' => 'Edit academic records', 'delete' => 'Delete academic records', 'manage' => 'Manage calendar and curriculum'],
        'offerings'     => ['view' => 'View class offerings', 'create' => 'Create class offerings', 'edit' => 'Edit class offerings', 'delete' => 'Delete class offerings'],
        'registrations' => ['view' => 'View course registrations', 'create' => 'Register students', 'edit' => 'Edit registrations', 'delete' => 'Drop registrations', 'approve' => 'Approve registrations'],
        'admissions'    => ['view' => 'View applications', 'create' => 'Capture applications', 'edit' => 'Edit applications', 'delete' => 'Delete applications', 'manage' => 'Manage intakes', 'review' => 'Review applications', 'enrol' => 'Enrol applicants'],
        'students'      => ['view' => 'View students', 'create' => 'Admit students', 'edit' => 'Edit student records', 'delete' => 'Remove students', 'status' => 'Change student status'],
        'staff'         => ['view' => 'View staff', 'create' => 'Add staff', 'edit' => 'Edit staff', 'delete' => 'Remove staff'],
        'teaching'      => ['access' => 'Access the lecturer workspace'],
        'portal'        => ['access' => 'Access the student portal'],
        'attendance'    => ['view' => 'View attendance', 'create' => 'Create attendance sessions', 'edit' => 'Mark attendance', 'delete' => 'Delete attendance sessions'],
        'timetable'     => ['view' => 'View the timetable', 'create' => 'Create timetable slots', 'edit' => 'Edit timetable slots', 'delete' => 'Delete timetable slots'],
        'exams'         => ['view' => 'View examinations', 'create' => 'Schedule examinations', 'edit' => 'Edit examinations', 'delete' => 'Delete examinations'],
        'results'       => ['view' => 'View results', 'create' => 'Enter marks', 'edit' => 'Amend marks', 'delete' => 'Delete results', 'manage' => 'Manage grading', 'publish' => 'Publish results'],
        'finance'       => ['view' => 'View finance records', 'create' => 'Create invoices and payments', 'edit' => 'Edit finance records', 'delete' => 'Delete finance records', 'manage' => 'Manage fee structures', 'reverse' => 'Reverse payments'],
        'payroll'       => ['view' => 'View payroll', 'create' => 'Run payroll', 'edit' => 'Edit payslips', 'delete' => 'Delete payroll periods'],
        'library'       => ['view' => 'View the library', 'create' => 'Add titles', 'edit' => 'Edit titles', 'delete' => 'Remove titles', 'issue' => 'Issue and receive books'],
        'hostel'        => ['view' => 'View accommodation', 'create' => 'Create hostels and rooms', 'edit' => 'Edit accommodation', 'delete' => 'Delete accommodation', 'allocate' => 'Allocate rooms'],
        'clearance'     => ['view' => 'View clearance', 'edit' => 'Sign off clearance'],
        'medical'       => ['view' => 'View health records', 'create' => 'Add health records', 'edit' => 'Edit health records', 'delete' => 'Delete health records'],
        'discipline'    => ['view' => 'View disciplinary cases', 'create' => 'Open cases', 'edit' => 'Update cases', 'delete' => 'Delete cases'],
        'tickets'       => ['view' => 'View support tickets', 'create' => 'Raise tickets', 'edit' => 'Respond to tickets', 'delete' => 'Delete tickets'],
        'hr'            => ['view' => 'View HR records', 'create' => 'Create HR records', 'edit' => 'Edit HR records', 'delete' => 'Delete HR records', 'manage' => 'Approve leave'],
        'documents'     => ['view' => 'View documents', 'create' => 'Upload documents', 'edit' => 'Edit documents', 'delete' => 'Delete documents'],
        'announcements' => ['view' => 'View announcements', 'create' => 'Publish announcements', 'edit' => 'Edit announcements', 'delete' => 'Delete announcements'],
        'events'        => ['view' => 'View events', 'create' => 'Create events', 'edit' => 'Edit events', 'delete' => 'Delete events'],
        'reports'       => ['view' => 'View reports', 'export' => 'Export reports'],
    ];

    $insertPermission = $pdo->prepare(
        'INSERT INTO permissions (name, slug, module, description) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)'
    );

    $count = 0;
    foreach ($modules as $module => $actions) {
        foreach ($actions as $action => $description) {
            $slug = $module . '.' . $action;
            $insertPermission->execute([ucfirst($action) . ' ' . str_replace('_', ' ', $module), $slug, $module, $description]);
            $count++;
        }
    }
    echo "  {$count} permissions.\n";

    /* ---------------- Roles ---------------- */
    $roles = [
        ['Super Administrator', 'super-admin', 1, 'Unrestricted access to every part of the system.', ['*']],
        ['Vice Chancellor', 'vice-chancellor', 2, 'Executive oversight with read access across the institution.',
            ['dashboard.view', 'reports.*', 'students.view', 'staff.view', 'academics.view', 'finance.view',
             'results.view', 'admissions.view', 'audit.view', 'announcements.*', 'events.*']],
        ['Registrar', 'registrar', 3, 'Owns admissions, student records and examinations.',
            ['dashboard.view', 'students.*', 'admissions.*', 'academics.*', 'offerings.*', 'registrations.*',
             'exams.*', 'results.*', 'timetable.*', 'attendance.view', 'reports.*', 'clearance.*',
             'announcements.*', 'events.*', 'documents.*', 'discipline.*', 'users.view']],
        ['Dean', 'dean', 4, 'Faculty-level academic oversight.',
            ['dashboard.view', 'academics.view', 'offerings.*', 'registrations.view', 'registrations.approve',
             'results.view', 'results.publish', 'students.view', 'staff.view', 'attendance.view', 'reports.view',
             'timetable.view', 'announcements.create', 'announcements.view']],
        ['Head of Department', 'hod', 5, 'Departmental academic management.',
            ['dashboard.view', 'academics.view', 'offerings.*', 'registrations.view', 'registrations.approve',
             'results.view', 'results.create', 'results.edit', 'students.view', 'staff.view',
             'attendance.view', 'timetable.*', 'reports.view', 'teaching.access']],
        ['Lecturer', 'lecturer', 6, 'Teaches classes, records attendance and enters marks.',
            ['dashboard.view', 'teaching.access', 'attendance.*', 'results.create', 'results.edit', 'results.view',
             'offerings.view', 'students.view', 'timetable.view', 'announcements.view', 'events.view',
             'documents.view', 'documents.create', 'library.view', 'hr.view']],
        ['Finance Officer', 'finance-officer', 5, 'Fees, invoicing, receipting and payroll.',
            ['dashboard.view', 'finance.*', 'payroll.*', 'students.view', 'reports.view', 'reports.export',
             'clearance.view', 'clearance.edit', 'announcements.view']],
        ['Librarian', 'librarian', 6, 'Manages the library catalogue and circulation.',
            ['dashboard.view', 'library.*', 'students.view', 'staff.view', 'clearance.view', 'clearance.edit',
             'reports.view', 'announcements.view']],
        ['Hostel Warden', 'warden', 6, 'Manages accommodation and room allocation.',
            ['dashboard.view', 'hostel.*', 'students.view', 'clearance.view', 'clearance.edit', 'announcements.view']],
        ['HR Officer', 'hr-officer', 5, 'Staff records, leave and payroll input.',
            ['dashboard.view', 'staff.*', 'hr.*', 'payroll.view', 'users.view', 'reports.view']],
        ['Admissions Officer', 'admissions-officer', 5, 'Processes applications and enrolment.',
            ['dashboard.view', 'admissions.*', 'students.view', 'students.create', 'academics.view', 'reports.view']],
        ['Medical Officer', 'medical-officer', 6, 'Maintains student health records.',
            ['dashboard.view', 'medical.*', 'students.view']],
        ['Support Desk', 'support-desk', 7, 'Handles the helpdesk queue.',
            ['dashboard.view', 'tickets.*', 'students.view', 'staff.view']],
        ['Student', 'student', 9, 'Access to the student self-service portal.',
            ['portal.access', 'announcements.view', 'events.view', 'tickets.view', 'tickets.create', 'library.view']],
        ['Parent / Guardian', 'guardian', 9, 'Read-only view of a linked student.',
            ['portal.access', 'announcements.view', 'events.view']],
    ];

    $insertRole = $pdo->prepare(
        'INSERT INTO roles (name, slug, description, level, is_system) VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), level = VALUES(level)'
    );

    $allPermissions = $pdo->query('SELECT id, slug FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);
    $slugToId       = array_flip($allPermissions);

    foreach ($roles as [$name, $slug, $level, $description, $grants]) {
        $insertRole->execute([$name, $slug, $description, $level]);
        $roleId = (int) $pdo->query("SELECT id FROM roles WHERE slug = " . $pdo->quote($slug))->fetchColumn();

        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$roleId]);

        $granted = [];
        foreach ($grants as $pattern) {
            if ($pattern === '*') {
                $granted = array_values($slugToId);
                break;
            }
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                foreach ($slugToId as $permSlug => $permId) {
                    if (str_starts_with($permSlug, $prefix)) {
                        $granted[] = $permId;
                    }
                }
                continue;
            }
            if (isset($slugToId[$pattern])) {
                $granted[] = $slugToId[$pattern];
            }
        }

        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        foreach (array_unique($granted) as $permissionId) {
            $link->execute([$roleId, $permissionId]);
        }
    }
    echo "  " . count($roles) . " roles with permission grants.\n";

    /* ---------------- Super administrator ---------------- */
    $adminExists = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
    if ($adminExists === 0) {
        $pdo->prepare(
            'INSERT INTO users (uuid, username, email, password_hash, title, first_name, last_name,
                                gender, phone, user_type, status, email_verified_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            App\Core\Hash::uuid4(),
            'admin',
            'admin@bestbrain.edu.lr',
            App\Core\Hash::make('Admin@2026'),
            'Mr',
            'System',
            'Administrator',
            'male',
            '+231770000000',
            'admin',
            'active',
        ]);
        $adminId = (int) $pdo->lastInsertId();
        $roleId  = (int) $pdo->query("SELECT id FROM roles WHERE slug = 'super-admin'")->fetchColumn();
        $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$adminId, $roleId]);
        echo "  Super administrator created (admin / Admin@2026).\n";
    } else {
        echo "  Super administrator already present.\n";
    }

    /* ---------------- Grading scale ---------------- */
    $scale = [
        ['A',  70.00, 100.00, 4.00, 'Excellent',     1],
        ['B',  60.00,  69.99, 3.00, 'Good',          1],
        ['C',  50.00,  59.99, 2.00, 'Satisfactory',  1],
        ['D',  40.00,  49.99, 1.00, 'Pass',          1],
        ['E',   0.00,  39.99, 0.00, 'Fail',          0],
    ];
    $insertGrade = $pdo->prepare(
        'INSERT INTO grade_scales (grade, min_score, max_score, grade_point, remarks, is_pass)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE min_score = VALUES(min_score), max_score = VALUES(max_score),
                                 grade_point = VALUES(grade_point), remarks = VALUES(remarks), is_pass = VALUES(is_pass)'
    );
    foreach ($scale as $band) {
        $insertGrade->execute($band);
    }
    echo "  " . count($scale) . " grade bands.\n";

    /* ---------------- Settings ---------------- */
    $settings = [
        ['institution_name', 'Best Brain University', 'general', 'string', 'Institution name'],
        ['institution_short_name', 'BBU', 'general', 'string', 'Short name / abbreviation'],
        ['institution_motto', 'Excellence in Education & Innovation', 'general', 'string', 'Motto'],
        ['institution_email', 'info@bestbrain.edu.lr', 'general', 'string', 'Official email'],
        ['institution_phone', '+231 77 000 000', 'general', 'string', 'Telephone'],
        ['institution_address', 'Monrovia, Liberia', 'general', 'text', 'Postal address'],
        ['institution_country', 'Liberia', 'general', 'string', 'Country'],
        ['institution_website', 'https://www.bestbrain.edu.lr', 'general', 'string', 'Website'],
        ['institution_logo', '', 'general', 'file', 'Institution Logo'],
        ['support_email', 'ict@bestbrain.edu.lr', 'general', 'string', 'ICT helpdesk email'],
        ['academic_year_label', '2025/2026', 'academic', 'string', 'Current academic year label'],
        ['min_credits_per_semester', '12', 'academic', 'integer', 'Minimum credit hours per semester'],
        ['max_credits_per_semester', '21', 'academic', 'integer', 'Maximum credit hours per semester'],
        ['pass_mark', '40', 'academic', 'integer', 'Overall pass mark (percent)'],
        ['coursework_weight', '30', 'academic', 'integer', 'Default coursework weighting (percent)'],
        ['exam_weight', '70', 'academic', 'integer', 'Default examination weighting (percent)'],
        ['attendance_threshold', '75', 'academic', 'integer', 'Minimum attendance to sit exams (percent)'],
        ['require_registration_approval', '1', 'academic', 'boolean', 'Course registrations need approval'],
        ['fee_threshold_percent', '60', 'finance', 'integer', 'Minimum fee paid (percent) before registration'],
        ['currency_code', 'USD', 'finance', 'string', 'Currency code'],
        ['currency_symbol', '$', 'finance', 'string', 'Currency symbol'],
        ['invoice_due_days', '30', 'finance', 'integer', 'Days until an invoice falls due'],
        ['late_payment_penalty', '0', 'finance', 'decimal', 'Late payment penalty (percent)'],
        ['library_loan_days', '14', 'library', 'integer', 'Standard loan period (days)'],
        ['library_borrow_limit', '3', 'library', 'integer', 'Titles a member may hold at once'],
        ['library_fine_per_day', '1.00', 'library', 'decimal', 'Overdue fine per day ($)'],
        ['library_max_renewals', '2', 'library', 'integer', 'Maximum renewals per loan'],
        ['hostel_application_open', '1', 'hostel', 'boolean', 'Accommodation applications are open'],
    ];
    $insertSetting = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value, setting_group, data_type, label)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE label = VALUES(label), setting_group = VALUES(setting_group), data_type = VALUES(data_type)'
    );
    foreach ($settings as $setting) {
        $insertSetting->execute($setting);
    }
    echo "  " . count($settings) . " settings.\n";

    /* ---------------- Reference data ---------------- */
    $leaveTypes = [
        ['Annual Leave', 21, 1, 'Paid annual leave entitlement.'],
        ['Sick Leave', 14, 1, 'Certified sick leave.'],
        ['Maternity Leave', 90, 1, 'Statutory maternity leave.'],
        ['Paternity Leave', 14, 1, 'Statutory paternity leave.'],
        ['Compassionate Leave', 5, 1, 'Bereavement and family emergencies.'],
        ['Study Leave', 30, 0, 'Unpaid leave for further study.'],
    ];
    $insertLeave = $pdo->prepare(
        'INSERT INTO leave_types (name, days_allowed, is_paid, description) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE days_allowed = VALUES(days_allowed)'
    );
    foreach ($leaveTypes as $type) {
        $insertLeave->execute($type);
    }

    $feeTypes = [
        ['TUI', 'Tuition Fee', 'tuition', 1, 1],
        ['REG', 'Registration Fee', 'registration', 1, 1],
        ['EXM', 'Examination Fee', 'examination', 1, 1],
        ['LIB', 'Library Fee', 'library', 1, 1],
        ['ICT', 'ICT and Computer Lab', 'other', 1, 1],
        ['MED', 'Medical Insurance', 'medical', 1, 1],
        ['ACT', 'Student Activity Fee', 'activity', 1, 1],
        ['CAU', 'Caution Money', 'other', 0, 1],
        ['ACC', 'Accommodation', 'accommodation', 1, 0],
        ['GRD', 'Graduation Fee', 'graduation', 0, 0],
        ['FIN', 'Fines and Penalties', 'fine', 0, 0],
    ];
    $insertFeeType = $pdo->prepare(
        'INSERT INTO fee_types (code, name, category, is_recurring, is_mandatory) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), category = VALUES(category)'
    );
    foreach ($feeTypes as $type) {
        $insertFeeType->execute($type);
    }

    $bookCategories = [
        ['GEN', 'General Reference'], ['SCI', 'Science and Technology'], ['ENG', 'Engineering'],
        ['BUS', 'Business and Economics'], ['LAW', 'Law'], ['MED', 'Health Sciences'],
        ['ART', 'Arts and Humanities'], ['EDU', 'Education'], ['ICT', 'Computing and ICT'],
    ];
    $insertCategory = $pdo->prepare(
        'INSERT INTO book_categories (code, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    foreach ($bookCategories as $category) {
        $insertCategory->execute($category);
    }
    echo "  Reference data (leave types, fee types, book categories).\n";
};
