<?php
/**
 * Sidebar navigation map.
 *
 * Each item may declare a permission; the link is hidden when the user lacks it.
 * An item may also declare 'user_type': these are personal workspaces backed by
 * a linked student/staff record, so holding the permission is not enough. The
 * super-admin role is granted every permission, which would otherwise advertise
 * "My Portal" and "My Classes" to administrators who have no such record.
 */
return [
    [
        'title' => 'Overview',
        'items' => [
            ['label' => 'Dashboard',     'url' => '/dashboard',     'icon' => 'dashboard',  'permission' => null, 'except_user_type' => ['student', 'parent']],
            ['label' => 'My Students',   'url' => '/family',        'icon' => 'student',    'permission' => null, 'user_type' => ['parent']],
            ['label' => 'Student Home',  'url' => '/portal',        'icon' => 'dashboard',  'permission' => 'portal.access',  'user_type' => ['student'], 'exact' => true],
        ],
    ],
    [
        'title' => 'My Studies',
        'items' => [
            ['label' => 'My Courses',      'url' => '/portal/courses',    'icon' => 'book',     'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Online Classes',  'url' => '/portal/classes',    'icon' => 'video',    'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Online Exams',    'url' => '/portal/exams',      'icon' => 'exam',     'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Assignments',     'url' => '/portal/assignments','icon' => 'file',     'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Timetable',       'url' => '/portal/timetable',  'icon' => 'clock',    'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Attendance',      'url' => '/portal/attendance', 'icon' => 'check',    'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Results',         'url' => '/portal/results',    'icon' => 'chart',    'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Transcript',      'url' => '/portal/transcript', 'icon' => 'file',     'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Degree Audit',    'url' => '/portal/degree-audit','icon' => 'check',   'permission' => 'portal.access', 'user_type' => ['student']],
        ],
    ],
    [
        'title' => 'My Account',
        'items' => [
            ['label' => 'Course Registration', 'url' => '/portal/registration', 'icon' => 'clipboard', 'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Fees &amp; Payments', 'url' => '/portal/finance',      'icon' => 'money',     'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'My Library',          'url' => '/portal/library',      'icon' => 'book',      'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Accommodation',       'url' => '/portal/hostel',       'icon' => 'bed',       'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'Clearance',           'url' => '/portal/clearance',    'icon' => 'shield',    'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'My Documents',        'url' => '/portal/documents',    'icon' => 'print',     'permission' => 'portal.access', 'user_type' => ['student']],
            ['label' => 'My Profile',          'url' => '/portal/profile',      'icon' => 'user',      'permission' => 'portal.access', 'user_type' => ['student']],
        ],
    ],
    [
        'title' => 'Teaching',
        'items' => [
            ['label' => 'My Classes',      'url' => '/teaching',              'icon' => 'clipboard', 'permission' => 'teaching.access', 'user_type' => ['lecturer', 'staff'], 'match' => '#/teaching(/\d+(/.*)?)?$#'],
            ['label' => 'Online Exams',    'url' => '/teaching/online-exams', 'icon' => 'exam',      'permission' => 'teaching.access', 'user_type' => ['lecturer', 'staff']],
        ],
    ],
    [
        'title' => 'Admissions',
        'items' => [
            ['label' => 'Applications',  'url' => '/admissions/applications', 'icon' => 'file',     'permission' => 'admissions.view'],
            ['label' => 'Intakes',       'url' => '/admissions/intakes',      'icon' => 'calendar', 'permission' => 'admissions.manage'],
        ],
    ],
    [
        'title' => 'People',
        'items' => [
            ['label' => 'Students',      'url' => '/students',      'icon' => 'student',  'permission' => 'students.view'],
            ['label' => 'Staff',         'url' => '/staff',         'icon' => 'users',    'permission' => 'staff.view'],
            ['label' => 'User Accounts', 'url' => '/admin/users',   'icon' => 'users',    'permission' => 'users.view'],
            ['label' => 'Roles',         'url' => '/admin/roles',   'icon' => 'shield',   'permission' => 'roles.view'],
        ],
    ],
    [
        'title' => 'Academics',
        'items' => [
            ['label' => 'Colleges',      'url' => '/academics/faculties',    'icon' => 'building', 'permission' => 'academics.view'],
            ['label' => 'Departments',   'url' => '/academics/departments',  'icon' => 'building', 'permission' => 'academics.view'],
            ['label' => 'Programmes',    'url' => '/academics/programs',     'icon' => 'book',     'permission' => 'academics.view'],
            ['label' => 'Courses',       'url' => '/academics/courses',      'icon' => 'book',     'permission' => 'academics.view'],
            ['label' => 'Academic Years','url' => '/academics/years',        'icon' => 'calendar', 'permission' => 'academics.manage'],
            ['label' => 'Semesters',     'url' => '/academics/semesters',    'icon' => 'calendar', 'permission' => 'academics.manage'],
            ['label' => 'Class Offerings','url' => '/academics/offerings',   'icon' => 'clipboard','permission' => 'offerings.view'],
            ['label' => 'Registrations', 'url' => '/academics/registrations','icon' => 'check',    'permission' => 'registrations.view'],
        ],
    ],
    [
        'title' => 'Teaching &amp; Assessment',
        'items' => [
            ['label' => 'Timetable',     'url' => '/timetable',        'icon' => 'clock',     'permission' => 'timetable.view'],
            ['label' => 'Attendance',    'url' => '/attendance',       'icon' => 'check',     'permission' => 'attendance.view'],
            ['label' => 'Examinations',  'url' => '/exams',            'icon' => 'clipboard', 'permission' => 'exams.view'],
            ['label' => 'Results',       'url' => '/exams/results',    'icon' => 'chart',     'permission' => 'results.view'],
            ['label' => 'Transcripts',   'url' => '/exams/transcripts','icon' => 'file',      'permission' => 'results.view'],
            ['label' => 'Grading Scale', 'url' => '/exams/grade-scale','icon' => 'settings',  'permission' => 'results.manage'],
        ],
    ],
    [
        'title' => 'Finance',
        'items' => [
            ['label' => 'Fee Types',      'url' => '/finance/fee-types',      'icon' => 'money', 'permission' => 'finance.manage'],
            ['label' => 'Tuition Rates',  'url' => '/finance/tuition-rates',  'icon' => 'money', 'permission' => 'finance.manage'],
            ['label' => 'Fee Structures', 'url' => '/finance/fee-structures', 'icon' => 'money', 'permission' => 'finance.manage'],
            ['label' => 'Invoices',       'url' => '/finance/invoices',       'icon' => 'file',  'permission' => 'finance.view'],
            ['label' => 'Payments',       'url' => '/finance/payments',       'icon' => 'money', 'permission' => 'finance.view'],
            ['label' => 'Online Payments','url' => '/finance/online-payments','icon' => 'money', 'permission' => 'finance.view'],
            ['label' => 'Scholarships',   'url' => '/finance/scholarships',   'icon' => 'money', 'permission' => 'finance.manage'],
            ['label' => 'Expenses',       'url' => '/finance/expenses',       'icon' => 'money', 'permission' => 'finance.manage'],
            ['label' => 'Payroll',        'url' => '/finance/payroll',        'icon' => 'money', 'permission' => 'payroll.view'],
        ],
    ],
    [
        'title' => 'Services',
        'items' => [
            ['label' => 'Library',        'url' => '/library/books',      'icon' => 'book',     'permission' => 'library.view'],
            ['label' => 'Book Loans',     'url' => '/library/loans',      'icon' => 'clipboard','permission' => 'library.view'],
            ['label' => 'Hostels',        'url' => '/hostel/hostels',     'icon' => 'bed',      'permission' => 'hostel.view'],
            ['label' => 'Room Allocation','url' => '/hostel/allocations', 'icon' => 'bed',      'permission' => 'hostel.view'],
            ['label' => 'Clearance',      'url' => '/services/clearance', 'icon' => 'check',    'permission' => 'clearance.view'],
            ['label' => 'Health Records', 'url' => '/services/medical',   'icon' => 'hospital', 'permission' => 'medical.view'],
            ['label' => 'Discipline',     'url' => '/services/discipline','icon' => 'shield',   'permission' => 'discipline.view'],
            ['label' => 'Support Desk',   'url' => '/services/tickets',   'icon' => 'mail',     'permission' => 'tickets.view'],
        ],
    ],
    [
        'title' => 'Human Resources',
        'items' => [
            ['label' => 'Leave Requests', 'url' => '/hr/leave',       'icon' => 'calendar', 'permission' => 'hr.view'],
            ['label' => 'Leave Types',    'url' => '/hr/leave-types', 'icon' => 'settings', 'permission' => 'hr.manage'],
        ],
    ],
    [
        'title' => 'Communication',
        'items' => [
            ['label' => 'Announcements',  'url' => '/announcements', 'icon' => 'bell',     'permission' => null],
            ['label' => 'Messages',       'url' => '/messages',      'icon' => 'mail',     'permission' => null],
            ['label' => 'Events',         'url' => '/events',        'icon' => 'calendar', 'permission' => null],
            ['label' => 'Documents',      'url' => '/documents',     'icon' => 'file',     'permission' => 'documents.view'],
        ],
    ],
    [
        'title' => 'Insights',
        'items' => [
            ['label' => 'Reports',        'url' => '/reports',       'icon' => 'chart',    'permission' => 'reports.view'],
        ],
    ],
    [
        'title' => 'System',
        'items' => [
            ['label' => 'Settings',       'url' => '/admin/settings', 'icon' => 'settings', 'permission' => 'settings.manage'],
            ['label' => 'Campuses',       'url' => '/admin/campuses', 'icon' => 'building', 'permission' => 'settings.manage'],
            ['label' => 'Rooms',          'url' => '/admin/rooms',    'icon' => 'building', 'permission' => 'settings.manage'],
            ['label' => 'Data Imports',   'url' => '/admin/data-imports', 'icon' => 'file', 'permission' => 'settings.manage'],
            ['label' => 'Audit Trail',    'url' => '/admin/audit',    'icon' => 'shield',   'permission' => 'audit.view'],
            ['label' => 'Signed-in Sessions', 'url' => '/admin/sessions', 'icon' => 'users', 'permission' => 'users.view'],
            ['label' => 'Email &amp; SMS Alerts', 'url' => '/admin/alerts', 'icon' => 'mail', 'permission' => 'settings.manage'],
            ['label' => 'Backups',        'url' => '/admin/backups',  'icon' => 'download', 'permission' => 'settings.manage'],
        ],
    ],
];
