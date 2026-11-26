<?php
/**
 * The access-control catalogue: every permission the software checks, and the
 * roles a university fills, each with the permissions that job needs.
 *
 * This file is the single source of truth. database/seeds/core.php applies it
 * on a fresh install (authoritative: a system role's grants are rebuilt), and
 * App\Services\AccessSync applies it to a running database (additive: missing
 * permissions, roles and grants are added, nothing is taken away).
 *
 * Grants accept "module.action", "module.*" for every action of a module, and
 * "*" for everything.
 */
declare(strict_types=1);

return [

    /* ---------------------------------------------------------------
     | Permissions, by module. Each slug is "module.action".
     * -------------------------------------------------------------*/
    'modules' => [
        'dashboard'     => ['view' => 'View dashboard'],
        'users'         => ['view' => 'View user accounts', 'create' => 'Create users', 'edit' => 'Edit users', 'delete' => 'Delete users', 'reset' => 'Reset passwords'],
        'roles'         => ['view' => 'View roles', 'create' => 'Create roles', 'edit' => 'Edit roles', 'delete' => 'Delete roles', 'assign' => 'Assign permissions'],
        'settings'      => ['view' => 'View system settings', 'create' => 'Add reference data', 'edit' => 'Edit reference data',
                            'delete' => 'Remove reference data', 'manage' => 'Manage system settings'],
        'audit'         => ['view' => 'View the audit trail'],
        'academics'     => ['view' => 'View academic structure', 'create' => 'Create academic records', 'edit' => 'Edit academic records', 'delete' => 'Delete academic records', 'manage' => 'Manage calendar and curriculum'],
        'elearning'     => ['view' => 'View online learning and online examinations', 'manage' => 'Schedule and manage online lessons and online examinations'],
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
        'graduation'    => ['view' => 'View graduation and alumni records', 'manage' => 'Clear and graduate students'],
        'advising'      => ['view' => 'View academic advising', 'manage' => 'Advise students and record notes'],
        'requests'      => ['view' => 'View student requests', 'review' => 'Decide student requests'],
        'evaluations'   => ['view' => 'View course evaluations', 'manage' => 'Run course evaluations'],
        'research'      => ['view' => 'View research output', 'create' => 'Record research output', 'edit' => 'Edit research output',
                            'delete' => 'Remove research output', 'manage' => 'Record research output for any staff member'],
        'finance'       => ['view' => 'View finance records', 'create' => 'Create invoices and payments', 'edit' => 'Edit finance records', 'delete' => 'Delete finance records', 'manage' => 'Manage fee structures', 'reverse' => 'Reverse payments'],
        'budgets'       => ['view' => 'View budgets', 'manage' => 'Set and revise budgets'],
        'payroll'       => ['view' => 'View payroll', 'create' => 'Run payroll', 'edit' => 'Edit payslips and salaries', 'delete' => 'Delete payroll periods',
                            'approve' => 'Approve a payroll run', 'pay' => 'Mark payroll as paid', 'export' => 'Export the payroll register'],
        'library'       => ['view' => 'View the library', 'create' => 'Add titles', 'edit' => 'Edit titles', 'delete' => 'Remove titles', 'issue' => 'Issue and receive books'],
        'hostel'        => ['view' => 'View accommodation', 'create' => 'Create hostels and rooms', 'edit' => 'Edit accommodation', 'delete' => 'Delete accommodation', 'allocate' => 'Allocate rooms'],
        'facilities'    => ['view' => 'View buildings and rooms', 'create' => 'Add buildings and rooms', 'edit' => 'Edit buildings and rooms', 'delete' => 'Remove buildings and rooms'],
        'assets'        => ['view' => 'View assets and internal supplies', 'manage' => 'Manage assets, supplies and maintenance'],
        'store'         => ['view' => 'View store products and sales', 'sell' => 'Sell at the store counter', 'manage' => 'Manage store products, prices and stock', 'void' => 'Void store sales'],
        'clearance'     => ['view' => 'View clearance', 'edit' => 'Sign off clearance'],
        'medical'       => ['view' => 'View health records', 'create' => 'Add health records', 'edit' => 'Edit health records', 'delete' => 'Delete health records'],
        'discipline'    => ['view' => 'View disciplinary cases', 'create' => 'Open cases', 'edit' => 'Update cases', 'delete' => 'Delete cases'],
        'clubs'         => ['view' => 'View clubs and societies', 'manage' => 'Manage clubs and membership'],
        'tickets'       => ['view' => 'View support tickets', 'create' => 'Raise tickets', 'edit' => 'Respond to tickets', 'delete' => 'Delete tickets'],
        'hr'            => ['view' => 'View HR records', 'create' => 'Create HR records', 'edit' => 'Edit HR records', 'delete' => 'Delete HR records', 'manage' => 'Approve leave'],
        'appraisals'    => ['view' => 'View staff appraisals', 'manage' => 'Run and score appraisals'],
        'recruitment'   => ['view' => 'View vacancies and applicants', 'manage' => 'Advertise posts and shortlist'],
        'documents'     => ['view' => 'View documents', 'create' => 'Upload documents', 'edit' => 'Edit documents', 'delete' => 'Delete documents'],
        'announcements' => ['view' => 'View announcements', 'create' => 'Publish announcements', 'edit' => 'Edit announcements', 'delete' => 'Delete announcements'],
        'events'        => ['view' => 'View events', 'create' => 'Create events', 'edit' => 'Edit events', 'delete' => 'Delete events'],
        'reports'       => ['view' => 'View reports', 'export' => 'Export reports'],
    ],

    /* ---------------------------------------------------------------
     | Grants to withdraw from a database that already has them.
     |
     | The sync is additive everywhere else, so a grant given by mistake would
     | otherwise stay for ever on a running system. Each entry is [role slug,
     | permission slug] and is removed once; an administrator who deliberately
     | grants it again by hand keeps it, because the entry only names what the
     | catalogue itself once got wrong.
     * -------------------------------------------------------------*/
    'revoke' => [
        // library.view is the staff catalogue and circulation desk: it lists
        // every borrower by name. Students search the catalogue and read their
        // own loans on their portal library page instead.
        ['student', 'library.view'],
    ],

    /* ---------------------------------------------------------------
     | Roles: [name, slug, level, description, grants].
     | Level 1 is the most senior; it orders listings and nothing else.
     | Each role carries the least access its job needs.
     * -------------------------------------------------------------*/
    'roles' => [

        /* ---- Governance and executive ---- */
        ['Super Administrator', 'super-admin', 1, 'Unrestricted access to every part of the system.', ['*']],

        ['President', 'president', 2, 'Chief executive of the university: oversight and read access across every office.',
            ['dashboard.view', 'reports.*', 'students.view', 'staff.view', 'academics.view', 'offerings.view', 'elearning.view', 'finance.view',
             'budgets.view', 'payroll.view', 'results.view', 'graduation.view', 'admissions.view', 'audit.view', 'assets.view',
             'hr.view', 'appraisals.view', 'research.view', 'evaluations.view', 'announcements.*', 'events.*', 'documents.view']],

        ['Vice Chancellor', 'vice-chancellor', 2, 'Executive oversight with read access across the institution.',
            ['dashboard.view', 'reports.*', 'students.view', 'staff.view', 'academics.view', 'elearning.view', 'finance.view', 'budgets.view',
             'results.view', 'graduation.view', 'admissions.view', 'audit.view', 'research.view', 'evaluations.view',
             'announcements.*', 'events.*']],

        ['Vice President for Academic Affairs', 'vp-academic', 2, 'Heads the academic division: programmes, teaching, results and graduation.',
            ['dashboard.view', 'academics.*', 'offerings.*', 'elearning.*', 'registrations.view', 'registrations.approve', 'timetable.view',
             'exams.view', 'results.view', 'results.publish', 'results.manage', 'graduation.*', 'advising.view', 'evaluations.*',
             'research.*', 'students.view', 'staff.view', 'appraisals.view', 'reports.*', 'announcements.*', 'events.view', 'documents.view']],

        ['Dean of Students', 'dean-of-students', 4, 'Student affairs: welfare, discipline, accommodation, clubs and student requests.',
            ['dashboard.view', 'students.view', 'discipline.*', 'clubs.*', 'hostel.*', 'requests.*', 'advising.*', 'medical.view',
             'tickets.view', 'tickets.edit', 'clearance.view', 'clearance.edit', 'reports.view', 'announcements.*', 'events.*']],

        ['Internal Auditor', 'internal-auditor', 4, 'Independent review of finance, payroll and records: reads everything, changes nothing.',
            ['dashboard.view', 'audit.view', 'reports.*', 'finance.view', 'budgets.view', 'payroll.view', 'assets.view', 'store.view',
             'students.view', 'staff.view', 'hr.view', 'library.view', 'admissions.view', 'results.view', 'documents.view']],

        /* ---- Registry and academic administration ---- */
        ['Registrar', 'registrar', 3, 'Owns admissions, student records, examinations and graduation.',
            ['dashboard.view', 'students.*', 'admissions.*', 'academics.*', 'offerings.*', 'elearning.*', 'registrations.*',
             'exams.*', 'results.*', 'timetable.*', 'attendance.view', 'reports.*', 'clearance.*', 'graduation.*',
             'requests.*', 'advising.*', 'facilities.view', 'announcements.*', 'events.*', 'documents.*', 'discipline.*', 'users.view']],

        ['Deputy Registrar', 'deputy-registrar', 4, 'Runs the registry day to day, short of deleting records.',
            ['dashboard.view', 'students.view', 'students.create', 'students.edit', 'students.status',
             'admissions.view', 'admissions.create', 'admissions.edit', 'admissions.review', 'admissions.enrol',
             'academics.view', 'offerings.view', 'offerings.create', 'offerings.edit', 'elearning.*',
             'registrations.view', 'registrations.create', 'registrations.edit', 'registrations.approve',
             'exams.view', 'exams.create', 'exams.edit', 'results.view', 'results.publish', 'timetable.*',
             'graduation.view', 'graduation.manage', 'requests.*', 'clearance.*', 'reports.*', 'documents.*']],

        ['Examinations Officer', 'exams-officer', 5, 'Schedules examinations, collates marks and publishes results.',
            ['dashboard.view', 'exams.*', 'results.*', 'elearning.*', 'timetable.*', 'registrations.view', 'offerings.view',
             'students.view', 'attendance.view', 'facilities.view', 'reports.*']],

        ['Quality Assurance Officer', 'quality-assurance', 5, 'Course evaluations, academic standards and institutional reporting.',
            ['dashboard.view', 'evaluations.*', 'elearning.view', 'research.view', 'appraisals.view', 'academics.view', 'offerings.view',
             'results.view', 'attendance.view', 'students.view', 'staff.view', 'reports.*']],

        ['Admissions Officer', 'admissions-officer', 5, 'Processes applications, offers and enrolment.',
            ['dashboard.view', 'admissions.*', 'students.view', 'students.create', 'academics.view',
             'requests.view', 'documents.view', 'documents.create', 'reports.view']],

        /* ---- Colleges, departments and teaching ---- */
        ['Dean', 'dean', 4, 'College-level academic oversight.',
            ['dashboard.view', 'academics.view', 'offerings.*', 'elearning.*', 'registrations.view', 'registrations.approve',
             'results.view', 'results.publish', 'students.view', 'staff.view', 'attendance.view', 'reports.view',
             'timetable.view', 'graduation.view', 'advising.view', 'evaluations.view', 'appraisals.view',
             'research.view', 'announcements.create', 'announcements.view']],

        ['Head of Department', 'hod', 5, 'Departmental academic management.',
            ['dashboard.view', 'academics.view', 'offerings.*', 'elearning.*', 'registrations.view', 'registrations.approve',
             'results.view', 'results.create', 'results.edit', 'students.view', 'staff.view',
             'attendance.view', 'timetable.*', 'advising.*', 'evaluations.view', 'appraisals.view',
             'research.view', 'research.create', 'reports.view', 'teaching.access']],

        ['Lecturer', 'lecturer', 6, 'Teaches classes, records attendance and enters marks.',
            ['dashboard.view', 'teaching.access', 'attendance.*', 'results.create', 'results.edit', 'results.view',
             'offerings.view', 'students.view', 'timetable.view', 'advising.view', 'evaluations.view',
             'research.view', 'research.create', 'research.edit', 'appraisals.view', 'announcements.view', 'events.view',
             'documents.view', 'documents.create', 'library.view', 'hr.view']],

        ['Teaching Assistant', 'teaching-assistant', 7, 'Supports a lecturer: attendance and draft marks only.',
            ['dashboard.view', 'teaching.access', 'attendance.view', 'attendance.create', 'attendance.edit',
             'results.view', 'results.create', 'offerings.view', 'students.view', 'timetable.view', 'library.view']],

        ['Academic Advisor', 'academic-advisor', 6, 'Guides students through their programme and registration.',
            ['dashboard.view', 'advising.*', 'students.view', 'results.view', 'registrations.view', 'registrations.approve',
             'offerings.view', 'graduation.view', 'requests.view', 'reports.view']],

        ['Research Officer', 'research-officer', 6, 'Keeps the record of research output and grants.',
            ['dashboard.view', 'research.*', 'staff.view', 'documents.view', 'documents.create', 'reports.view']],

        /* ---- Finance ---- */
        ['Bursar', 'bursar', 3, 'Heads finance: fees, payments, budgets, payroll and assets.',
            ['dashboard.view', 'finance.*', 'budgets.*', 'payroll.*', 'assets.view', 'store.*', 'students.view', 'staff.view',
             'clearance.view', 'clearance.edit', 'reports.*', 'audit.view', 'announcements.view']],

        ['Finance Officer', 'finance-officer', 5, 'Fees, invoicing, receipting and payroll.',
            ['dashboard.view', 'finance.*', 'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.export',
             'budgets.view', 'store.view', 'students.view', 'reports.view', 'reports.export',
             'clearance.view', 'clearance.edit', 'announcements.view']],

        ['Accountant', 'accountant', 5, 'Keeps the books: invoices, payments, expenses and budget lines.',
            ['dashboard.view', 'finance.view', 'finance.create', 'finance.edit', 'finance.manage',
             'budgets.view', 'budgets.manage', 'payroll.view', 'payroll.export', 'store.view', 'students.view', 'reports.*']],

        ['Cashier', 'cashier', 6, 'Receives payments at the counter and issues receipts.',
            ['dashboard.view', 'finance.view', 'finance.create', 'store.view', 'store.sell', 'students.view', 'staff.view', 'clearance.view', 'reports.view']],

        ['Payroll Officer', 'payroll-officer', 5, 'Prepares the monthly payroll: salaries, allowances, loans and payslips.',
            ['dashboard.view', 'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.export',
             'staff.view', 'hr.view', 'finance.view', 'reports.view', 'reports.export']],

        ['Procurement Officer', 'procurement-officer', 5, 'Buys for the university and keeps the asset register.',
            ['dashboard.view', 'assets.*', 'budgets.view', 'finance.view', 'facilities.view', 'reports.view']],

        ['Stores Officer', 'stores-officer', 6, 'Receives, issues and counts internal supplies.',
            ['dashboard.view', 'assets.*', 'reports.view']],

        ['Store Manager', 'store-manager', 5, 'Runs the university store: products, prices, stock and sales to students and staff.',
            ['dashboard.view', 'store.*', 'students.view', 'staff.view', 'reports.view']],

        ['Store Attendant', 'store-attendant', 7, 'Sells at the store counter and issues receipts.',
            ['dashboard.view', 'store.view', 'store.sell', 'students.view', 'staff.view']],

        /* ---- Human resources ---- */
        ['HR Officer', 'hr-officer', 5, 'Staff records, leave, appraisal, recruitment and payroll input.',
            ['dashboard.view', 'staff.*', 'hr.*', 'appraisals.*', 'recruitment.*',
             'payroll.view', 'payroll.edit', 'users.view', 'documents.view', 'reports.view', 'reports.export']],

        /* ---- Services and operations ---- */
        ['ICT Administrator', 'ict-administrator', 3, 'Runs the system: accounts, roles, settings and the helpdesk.',
            ['dashboard.view', 'users.*', 'roles.*', 'settings.*', 'audit.view', 'tickets.*',
             'facilities.view', 'assets.view', 'staff.view', 'students.view', 'reports.view']],

        ['Support Desk', 'support-desk', 7, 'Handles the helpdesk queue.',
            ['dashboard.view', 'tickets.*', 'students.view', 'staff.view']],

        ['Facilities Officer', 'facilities-officer', 5, 'Buildings, lecture rooms, laboratories and venue bookings.',
            ['dashboard.view', 'facilities.*', 'assets.*', 'timetable.view', 'exams.view', 'hostel.view', 'reports.view']],

        ['Librarian', 'librarian', 6, 'Manages the library catalogue and circulation.',
            ['dashboard.view', 'library.*', 'students.view', 'staff.view', 'clearance.view', 'clearance.edit',
             'reports.view', 'announcements.view']],

        ['Library Assistant', 'library-assistant', 7, 'Issues and receives books at the desk.',
            ['dashboard.view', 'library.view', 'library.issue', 'students.view', 'staff.view']],

        ['Hostel Warden', 'warden', 6, 'Manages accommodation and room allocation.',
            ['dashboard.view', 'hostel.*', 'students.view', 'discipline.view',
             'clearance.view', 'clearance.edit', 'announcements.view']],

        ['Security Officer', 'security-officer', 7, 'Campus security: incident reports and gate records.',
            ['dashboard.view', 'discipline.view', 'discipline.create', 'students.view', 'staff.view',
             'facilities.view', 'events.view']],

        /* ---- Student welfare ---- */
        ['Medical Officer', 'medical-officer', 6, 'Maintains student health records.',
            ['dashboard.view', 'medical.*', 'students.view', 'clearance.view']],

        ['Nurse', 'nurse', 7, 'Runs the clinic and records consultations.',
            ['dashboard.view', 'medical.view', 'medical.create', 'medical.edit', 'students.view']],

        ['Student Counsellor', 'counsellor', 6, 'Confidential guidance and counselling.',
            ['dashboard.view', 'advising.*', 'students.view', 'requests.view', 'discipline.view', 'medical.view']],

        ['Sports Officer', 'sports-officer', 6, 'Sport, clubs and societies.',
            ['dashboard.view', 'clubs.*', 'events.*', 'students.view', 'facilities.view', 'announcements.view']],

        ['Chaplain', 'chaplain', 7, 'Chaplaincy and student spiritual life.',
            ['dashboard.view', 'students.view', 'clubs.view', 'events.view', 'events.create', 'announcements.view']],

        /* ---- Communications and outreach ---- */
        ['Public Relations Officer', 'public-relations', 6, 'Announcements, events, publicity and the public face of the university.',
            ['dashboard.view', 'announcements.*', 'events.*', 'documents.view', 'documents.create',
             'students.view', 'staff.view', 'recruitment.view', 'reports.view']],

        ['Alumni Officer', 'alumni-officer', 6, 'Keeps in touch with graduates and runs alumni events.',
            ['dashboard.view', 'graduation.view', 'students.view', 'announcements.*', 'events.*', 'reports.view']],

        ['Front Desk', 'front-desk', 8, 'Reception: directs enquiries and raises tickets.',
            ['dashboard.view', 'students.view', 'staff.view', 'tickets.view', 'tickets.create', 'tickets.edit',
             'admissions.view', 'announcements.view', 'events.view']],

        /* ---- The people the university serves ---- */
        // No library.view: that is the staff catalogue and circulation desk, which
        // lists every borrower. Students search the catalogue and see their own
        // loans on their portal library page, under portal.access.
        ['Student', 'student', 9, 'Access to the student self-service portal.',
            ['portal.access', 'announcements.view', 'events.view', 'tickets.view', 'tickets.create']],

        ['Alumnus', 'alumni', 9, 'A graduate: keeps portal access to records and alumni news.',
            ['portal.access', 'announcements.view', 'events.view']],

        ['Parent / Guardian', 'guardian', 9, 'Read-only view of a linked student.',
            ['portal.access', 'announcements.view', 'events.view']],
    ],
];
