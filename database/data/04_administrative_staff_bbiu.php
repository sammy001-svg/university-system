<?php
/**
 * Administrative and support staff, transcribed from "BBIU ADMINISTRATIVE STAFF.pdf".
 *
 * Imported automatically on the next page load after this file changes
 * (Admin > Data Imports shows the result). By hand:  php database/data_import.php database/data/04_administrative_staff_bbiu.php
 * Personal data: the repository must stay private.
 *
 * Each row: printed      - the line exactly as in the PDF
 *           title / first / other / last - the parsed name ("other" = middle names/initials)
 *           sex          - from the title only (Mr. = male, Mrs./Ms. = female); otherwise null
 *           designation  - job title, written out in full (the PDF wording is in "printed")
 *           category     - staff category: academic, administrative, technical or support
 *           role         - system role (least access that fits the job), or null for none
 *           login        - false: staff record only; the account exists but cannot sign in
 *           note         - anything the registrar or HR should confirm
 */
declare(strict_types=1);

$s = static fn (string $printed, ?string $title, string $first, ?string $other, string $last, ?string $sex,
                string $designation, string $category, ?string $role, bool $login, ?string $note = null): array =>
    compact('printed', 'title', 'first', 'other', 'last', 'sex', 'designation', 'category', 'role', 'login', 'note');

return [
    'type'   => 'staff',
    'source' => 'BBIU ADMINISTRATIVE STAFF.pdf',
    'staff'  => [
        // Leadership and administration
        $s('Rev. Dr. J. Luther Tarpeh - President', 'Rev. Dr.', 'J.', 'Luther', 'Tarpeh', null,
           'President', 'administrative', 'vice-chancellor', true,
           'Given the Vice Chancellor role: executive, read-only access across the institution.'),
        $s('Mrs. Christine A. Tarpeh - V P. Academic Affairs', 'Mrs.', 'Christine', 'A.', 'Tarpeh', 'female',
           'Vice President for Academic Affairs', 'administrative', 'vice-chancellor', true,
           'Printed "V P. Academic Affairs". Given the Vice Chancellor role (executive, read-only); the Registrar role would also let her change academic records.'),
        $s('Rev. Dr. James Partee Toga - Dean for student affairs', 'Rev. Dr.', 'James', 'Partee', 'Toga', null,
           'Dean for Student Affairs', 'academic', null, true,
           'Same person as lecturer "Rev. Dr. James P. Toga" (College of Liberal Arts): his existing record is updated, and he keeps the Lecturer role.'),
        $s('Mrs. Retta P. Amet - Director of Human Resource', 'Mrs.', 'Retta', 'P.', 'Amet', 'female',
           'Director of Human Resources', 'administrative', 'hr-officer', true, 'Printed "Director of Human Resource".'),
        $s('Mrs. Jerrlyn J. K. Menyongar Kafel - Accountant', 'Mrs.', 'Jerrlyn', 'J. K. Menyongar', 'Kafel', 'female',
           'Accountant', 'administrative', 'finance-officer', true,
           'Surname taken as "Kafel" with "Menyongar" as a middle (likely maiden) name; confirm, or whether it should be "Menyongar Kafel".'),
        $s('Ms. Blessing Samuela Bruce - Coordinator of Admission', 'Ms.', 'Blessing', 'Samuela', 'Bruce', 'female',
           'Coordinator of Admissions', 'administrative', 'admissions-officer', true, 'Printed "Coordinator of Admission".'),

        // ICT and library
        $s('Mr. Joe B. Bestman - IT Assistant', 'Mr.', 'Joe', 'B.', 'Bestman', 'male',
           'IT Assistant', 'technical', 'support-desk', true),
        $s('Mr. Lawton Nyankun Zeegay - IT Assistant', 'Mr.', 'Lawton', 'Nyankun', 'Zeegay', 'male',
           'IT Assistant', 'technical', 'support-desk', true),
        $s('Mr. Joseph Arkoi - Librarian', 'Mr.', 'Joseph', null, 'Arkoi', 'male',
           'Librarian', 'administrative', 'librarian', true),

        // Security and facilities: staff records only, no system access
        $s('Mr. Alex B. Kojo - Head Security', 'Mr.', 'Alex', 'B.', 'Kojo', 'male',
           'Head of Security', 'support', null, false, 'Printed "Head Security".'),
        $s('Mr. Manah Zah - Security', 'Mr.', 'Manah', null, 'Zah', 'male',
           'Security Officer', 'support', null, false, 'Printed "Security".'),
        $s('Mr. Jallah Morris - Custodian', 'Mr.', 'Jallah', null, 'Morris', 'male',
           'Custodian', 'support', null, false),
        $s('Mr. Junior Zaime - Janitor', 'Mr.', 'Junior', null, 'Zaime', 'male',
           'Janitor', 'support', null, false, '"Junior" is taken as his first name, not the suffix "Jr.".'),
        $s('Mr. Olixcar Seegbay - Head of Night security', 'Mr.', 'Olixcar', null, 'Seegbay', 'male',
           'Head of Night Security', 'support', null, false, 'First name spelled "Olixcar" as printed; confirm the spelling.'),
        $s('Mr. Joseph Morgan - Night security', 'Mr.', 'Joseph', null, 'Morgan', 'male',
           'Night Security Officer', 'support', null, false, 'Printed "Night security".'),
        $s('Mr. Saturday Parbu - Night security', 'Mr.', 'Saturday', null, 'Parbu', 'male',
           'Night Security Officer', 'support', null, false, 'Printed "Night security".'),
    ],
];
