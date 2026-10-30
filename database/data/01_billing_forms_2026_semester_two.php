<?php
/**
 * Students from the Student's Billing Forms (Office of Admission and Records),
 * Academic Year 2026, Semester Two. Transcribed exactly as printed.
 *
 * Personal data: the repository must stay private.
 * Imported automatically on the next page load after this file changes
 * (Admin > Data Imports shows the result). By hand:  php database/data_import.php database/data/01_billing_forms_2026_semester_two.php
 *
 * courses: [code as printed, title as printed, section, credit hours, cost]
 * expected: the totals printed on the form; the import refuses to save a
 * student whose bill in the system does not match them to the cent.
 */
declare(strict_types=1);

return [
    'type'          => 'billing_forms',
    'academic_year' => '2026',
    'semester'      => 'Semester Two',
    'source'        => "Student's Billing Form, Office of Admission and Records",
    'students'      => [
        [
            'id'         => 'BBIU2026100162',
            'last_name'  => 'Greaves',
            'first_name' => 'Judith',
            'other_name' => null,
            'sex'        => 'female',
            'phone'      => '0760162598',
            'email'      => null,
            'country'    => null,
            'program'    => 'BSN',               // Department: Nursing; College: "School of Health Sciences"
            'standing'   => 'Freshman I',
            'courses'    => [
                ['ENG 033', 'English Skills', 'One', 3, 30.00],
                ['BIOL 101', 'General Biology I', 'One', 4, 72.00],
                ['NURS 101', 'Fundamentals of Nursing', 'One', 3, 54.00],
                ['COSC 101', 'General Computer Literacy', 'One', 3, 30.00],
                ['CHEM 101', 'Introduction to (Inorganic) Chemistry I', 'One', 4, 72.00],
            ],
            'expected'   => ['credits' => 17, 'tuition' => 258.00, 'registration_fees' => 180.00, 'grand_total' => 438.00, 'first_installment' => 309.00],
        ],
        [
            'id'         => 'BBIU2026100157',
            'last_name'  => 'Todd',
            'first_name' => 'Rosalyn',
            'other_name' => 'M.',
            'sex'        => 'female',
            'phone'      => '0772606232',
            'email'      => null,
            'country'    => null,
            'program'    => 'BBAMGT',            // Department: Management; College: "School of Business"
            'standing'   => 'Freshman II',
            'courses'    => [
                ['ENGL 101', 'Freshman English I', 'One', 3, 30.00],
                ['MATH 107', 'Pre-Calculus', 'One', 4, 40.00],
                ['SOSC 101', 'Introduction to Social Science', 'One', 3, 30.00],
                ['COSC 102', 'General Computer Literacy 2', 'Three', 3, 30.00],
                ['PHED 102', 'Physical Education II', 'One', 1, 10.00],
                ['ACCT 102', 'Introduction to Accounting II', 'One', 3, 30.00],
            ],
            'expected'   => ['credits' => 17, 'tuition' => 170.00, 'registration_fees' => 143.00, 'grand_total' => 313.00, 'first_installment' => 228.00],
        ],
        [
            'id'         => 'BBIU2025100060',
            'last_name'  => 'Jamina',
            'first_name' => 'James',
            'other_name' => 'L.',
            'sex'        => 'male',
            'phone'      => '0778761868',
            'email'      => null,
            'country'    => null,
            'program'    => 'BSIT',              // Department: Information Technology; College: "School of Information Technology"
            'standing'   => 'Sophomore I',
            'courses'    => [
                ['CISC 213', 'Database Management System (MYSQL)', 'One', 3, 75.00],
                ['CISC 205', 'Data Communication', 'One', 3, 75.00],
                ['ENGL 102', 'Freshman English II', 'One', 3, 30.00],
                ['CISC 207', 'Introduction to web Development', 'One', 3, 75.00],
                ['CISC 201', 'Computer Troubleshooting & Software Maintenance', 'One', 3, 75.00],
                ['IT 212', 'Organization Behavior', 'One', 3, 75.00],
            ],
            'expected'   => ['credits' => 18, 'tuition' => 405.00, 'registration_fees' => 153.00, 'grand_total' => 558.00, 'first_installment' => 355.50],
        ],
    ],
];
