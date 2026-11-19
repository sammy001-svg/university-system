<?php
/**
 * Core seed: permissions, roles, the super administrator, settings and the
 * grading scale. Safe to re-run - everything uses INSERT IGNORE / upserts.
 */
declare(strict_types=1);

return static function (PDO $pdo): void {

    /* ---------------- Permissions and roles ---------------- */
    // The catalogue lives in database/seeds/access.php; a fresh install applies it
    // authoritatively, so every system role's grants match the file exactly.
    $access = App\Services\AccessSync::apply($pdo, dirname(__DIR__, 2), true);
    echo "  {$access['permissions']} permissions.
";
    echo "  {$access['roles']} roles with permission grants.
";

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
    // Matches database/seeds/catalog/bbiu.php; the academic seed re-applies it.
    $scale = [
        ['A',  90.00, 100.00, 4.00, 'Excellent', 1],
        ['B',  80.00,  89.99, 3.00, 'Very Good', 1],
        ['C',  70.00,  79.99, 2.00, 'Good',      1],
        ['D',  60.00,  69.99, 1.00, 'Pass',      1],
        ['F',   0.00,  59.99, 0.00, 'Fail',      0],
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
        ['institution_name', 'Best Brains International University', 'general', 'string', 'Institution name'],
        ['institution_short_name', 'BBIU', 'general', 'string', 'Short name / abbreviation'],
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
        ['pass_mark', '60', 'academic', 'integer', 'Overall pass mark (percent)'],
        ['coursework_weight', '30', 'academic', 'integer', 'Default coursework weighting (percent)'],
        ['exam_weight', '70', 'academic', 'integer', 'Default examination weighting (percent)'],
        ['attendance_threshold', '75', 'academic', 'integer', 'Minimum attendance to sit exams (percent)'],
        ['require_registration_approval', '1', 'academic', 'boolean', 'Course registrations need approval'],
        ['fee_threshold_percent', '60', 'finance', 'integer', 'Default 1st installment (percent of tuition) when a fee structure has no installment plan'],
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
        ['ADM', 'Admission Fee', 'registration', 0, 1],
        ['ORI', 'Orientation Fee', 'activity', 0, 1],
        ['IDC', 'Student ID Card', 'other', 0, 1],
        ['LAB', 'Laboratory Fee', 'other', 1, 1],
        ['CLN', 'Clinical Practicum Fee', 'other', 1, 1],
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
