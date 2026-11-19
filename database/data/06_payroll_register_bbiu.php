<?php
/**
 * The payroll register's staff list, transcribed from the payroll sheet in
 * "BBIU Income & Expenditure, Payroll, Reports, daily receivables, Templates.pdf".
 *
 * Imported automatically on the next page load after this file changes
 * (Admin > Data Imports shows the result). By hand:
 *   php database/data_import.php database/data/06_payroll_register_bbiu.php
 * Personal data: the repository must stay private.
 *
 * What this file adds to records already in the system: a telephone number,
 * sex, and the college a lecturer is posted to. It carries no salary figures -
 * the sheet's money columns are blank, and salaries are entered in
 * Finance > Staff Salaries, where they are kept out of the repository.
 *
 * Matching. The sheet writes several names differently from the lecturer and
 * administrative staff lists ("Luther J Tarpeh" for "J. Luther Tarpeh",
 * "Morris Jallah" for "Jallah Morris", "Merlin" for "Melin"). Each row below
 * is therefore written in the form the existing record uses, with the sheet's
 * own wording kept in "printed" so the two can be compared. Rows marked
 * create = true are people the sheet adds who are in no earlier list; every
 * other row only ever updates an existing record, and anything that cannot be
 * matched is reported for HR rather than guessed at.
 *
 * Still to confirm with HR:
 *   - Joseph Morgan and Saturday Parbu (night security) are on the staff list
 *     but not on this payroll sheet.
 *   - Sayou Tequah Jr. (Liberal Arts) and Roland Oghogho (Business) are in the
 *     lecturer list but not on this sheet.
 *   - Dates of birth and employee IDs are blank on the sheet throughout.
 *
 * Each row: printed / first / other / last / sex / phone / position / college
 *           / create / category / role / login / note
 */
declare(strict_types=1);

$p = static fn (string $printed, string $first, ?string $other, string $last, ?string $sex, ?string $phone,
                string $position, ?string $college, bool $create = false, string $category = 'administrative',
                ?string $role = null, bool $login = false, ?string $note = null): array =>
    ['printed' => $printed, 'first' => $first, 'other' => $other, 'last' => $last, 'sex' => $sex,
     'phone' => $phone, 'dob' => null, 'position' => $position, 'college' => $college, 'department' => null,
     'create' => $create, 'category' => $category, 'role' => $role, 'login' => $login, 'note' => $note];

// The colleges as the academic catalogue names them; the sheet abbreviates two of them.
$CLASS = 'College of Liberal Arts & Social Sciences';
$CHS   = 'College of Health Science';
$COB   = 'College of Business';
$CIT   = 'College of Information Technology';
$COE   = 'College of Education';

return [
    'type'   => 'staff_payroll',
    'source' => 'BBIU Income & Expenditure, Payroll, Reports, daily receivables, Templates.pdf (Payroll sheet)',
    'staff'  => [

        /* ---- Administration ---- */
        $p('Rev. Dr. Luther J Tarpeh - President', 'J.', 'Luther', 'Tarpeh', 'male', '0776-323-643',
           'President', null, false, 'administrative', null, false,
           'The sheet prints "Luther J Tarpeh"; the staff list has "J. Luther Tarpeh".'),
        $p('Mrs. Christine A Tarpeh - Vice President for Academic Affairs', 'Christine', 'A.', 'Tarpeh', 'female', '0776-199-708',
           'Vice President for Academic Affairs', null),
        $p('Rev. Dr. James Partee Toga - Deam for Students Affairs', 'James', 'Partee', 'Toga', 'male', '0770-519-394',
           'Dean for Student Affairs', null, false, 'academic', null, false, 'Printed "Deam for Students Affairs".'),
        $p('Mrs. Retta P Amet - Director of Human Resource', 'Retta', 'P.', 'Amet', 'female', '0777-875-566',
           'Director of Human Resources', null),
        $p('Mrs. Jerrlyn JK Menyongar Kafel - Accountant', 'Jerrlyn', 'J. K. Menyongar', 'Kafel', 'female', '0770-370-894',
           'Accountant', null),
        $p('Blessing Samuela Bruce - Coordinator of Admission', 'Blessing', 'Samuela', 'Bruce', 'female', '0778-207-543',
           'Coordinator of Admissions', null),
        $p('Joe B Bestman - IT Assistant', 'Joe', 'B.', 'Bestman', 'male', '0770-043-296',
           'IT Assistant', null, false, 'technical'),
        $p('Lawton Nyankun Zeegay - IT Assistant', 'Lawton', 'Nyankun', 'Zeegay', 'male', '0776-153-849',
           'IT Assistant', null, false, 'technical'),
        $p('Joseph Arkoi - Librarian', 'Joseph', null, 'Arkoi', 'male', '0779-323-261',
           'Librarian', null),
        $p('Elijah T Johnson - Receptionist', 'Elijah', 'T.', 'Johnson', 'male', '0777-853-115',
           'Receptionist', null, true, 'administrative', 'front-desk', true,
           'Not in the administrative staff list; added from this sheet.'),
        $p('Terry Kezelee - Photocopier/Publicity', 'Terry', null, 'Kezelee', 'male', '0775-142-271',
           'Publicity and Reprographics Officer', null, true, 'support', null, false,
           'Not in the administrative staff list; added from this sheet. Printed "Photocopier/Publicity".'),
        $p('Alex B Kojo - Head Security', 'Alex', 'B.', 'Kojo', 'male', '0777-747-910',
           'Head of Security', null, false, 'support'),
        $p('Manah Zah - Day Security', 'Manah', null, 'Zah', 'male', '0775-235-828',
           'Day Security Officer', null, false, 'support', null, false,
           'The staff list records him simply as "Security"; this sheet says Day Security.'),
        $p('Morris Jallah - Janitor', 'Jallah', null, 'Morris', 'male', '0773-655-835',
           'Janitor', null, false, 'support', null, false,
           'The sheet prints the name reversed ("Morris Jallah"); the staff list has "Jallah Morris", recorded there as Custodian.'),
        $p('Junior Zaime - Janitor', 'Junior', null, 'Zaime', 'male', '0772-042-169',
           'Janitor', null, false, 'support'),
        $p('Olixcar Seegbay - Night Security', 'Olixcar', null, 'Seegbay', 'male', '0776-924-364',
           'Head of Night Security', null, false, 'support'),
        $p('Blessing O Kennedy - Night security', 'Blessing', 'O.', 'Kennedy', 'male', '0778-753-041',
           'Night Security Officer', null, true, 'support', null, false,
           'Not in the administrative staff list; added from this sheet.'),
        $p('NDW Waste Management - Garbage Collector', 'NDW', null, 'Waste Management', null, '0776-929-378',
           'Garbage Collection', null, true, 'support', null, false,
           'A contractor, not a person. Kept on the payroll because the sheet pays it; move it to Expenses if Finance prefers.'),

        /* ---- College of Liberal Arts & Social Sciences ---- */
        $p('Garmondah Yebleh - Lecturer', 'Garmondeh', null, 'Yebleh', 'male', '0775-305-645',
           'Lecturer', $CLASS, false, 'academic', null, false, 'The sheet spells the first name "Garmondah"; the lecturer list has "Garmondeh".'),
        $p('Marcathy S. Hne Jr. - Lecturer', 'Marcathy', 'S.', 'Hne', 'male', '0886-818-433',
           'Lecturer', $CLASS, false, 'academic', null, false, 'The sheet adds the suffix "Jr."; confirm it belongs on the record.'),
        $p('Dosla Pius Wreh - Lecturer', 'Pius', null, 'Wreh', 'male', '0770-211-673',
           'Lecturer', $CLASS, false, 'academic', null, false,
           'The sheet gives a further forename, "Dosla"; the lecturer list has only "Pius Wreh".'),
        $p('Cllr. Anthony D Kromah - Lecturer', 'Anthony', 'D.', 'Kromah', 'male', '0777-727-727',
           'Lecturer', $CLASS, false, 'academic'),
        $p('Tuah Saytue Sneh - Lecturer', 'Tuah', 'Saytue', 'Sneh', 'male', '0776-876-075',
           'Lecturer', $CLASS, false, 'academic'),
        $p('Cooper Mykers - Lecturer', 'Cooper', null, 'Mykers', 'male', '0770-033-748',
           'Lecturer', $CLASS, false, 'academic'),
        $p('Jacqueline Tuan Thomas - Lecturer', 'Jacqueline', 'Tuan', 'Thomas', 'female', '0770-535-296',
           'Lecturer', $CLASS, false, 'academic'),
        $p('Christopher Sellee - Lecturer', 'Christopher', null, 'Sellee', 'male', '0775-114-278',
           'Lecturer', $CLASS, false, 'academic'),
        $p('Momodu Dukuly - Lecturer', 'Momodu', null, 'Dukuly', 'male', '0775-252-492',
           'Lecturer', $CLASS, false, 'academic'),
        $p('Francis Jr. Pelena - Lecturer', 'Francis', null, 'Pelena Jr.', 'male', '0777-588-547',
           'Lecturer', $CLASS, false, 'academic', null, false,
           'The sheet splits the suffix off as "Francis Jr. | Pelena"; the lecturer list has "Francis Pelena Jr.".'),
        $p('Anthony F Kromah - Lecturer', 'Anthony', 'F.', 'Kromah', 'male', '0770-149-870',
           'Lecturer', $CLASS, false, 'academic', null, false,
           'The second Anthony Kromah. The telephone numbers differ from Cllr. Anthony D. Kromah, which supports their being two people.'),
        $p('Milton Yoko - Instructor', 'Milton', null, 'Yoko', 'male', '0777-321-355',
           'Instructor', $CLASS, false, 'academic'),

        /* ---- College of Health Science ---- */
        $p('Linda R Keah - Lecturer', 'Linda', 'R.', 'Keah', 'female', '0777-084-321',
           'Lecturer', $CHS, false, 'academic'),
        $p('Dr. Emmanuel P Flomo - Lecturer', 'Emmanuel', null, 'Flomo', 'male', '0770-464-209',
           'Lecturer', $CHS, false, 'academic', null, false, 'The sheet gives a middle initial "P" the lecturer list does not have.'),
        $p('Dr. Emmanuel Olatunji Ogunleye - Lecturer', 'Emmanuel', 'Olatunji', 'Ogunleye', 'male', '0777-404-062',
           'Lecturer', $CHS, false, 'academic'),
        $p('Dr. Trokon G Richards - Lecturer', 'G.', 'Trokon', 'Richards', 'male', '0770-986-287',
           'Lecturer', $CHS, false, 'academic', null, false, 'The sheet orders the names "Trokon G"; the lecturer list has "G. Trokon".'),
        $p('Augustine N Marwolo - Lecturer', 'Augustine', null, 'Marwolo', 'male', '0777-561-142',
           'Lecturer', $CHS, false, 'academic', null, false, 'The sheet gives a middle initial "N" the lecturer list does not have.'),
        $p('Kolubah LF Wriyee - Instructor', 'F.', 'Kolubah L.', 'Wriyee', 'male', '0776-239-947',
           'Instructor', $CHS, false, 'academic', null, false, 'The sheet orders the names "Kolubah LF"; the lecturer list has "F. Kolubah L.".'),
        $p('Dr. Gondah Lepkeh - Lecturer', 'Gondah', null, 'Lepkeh', 'male', '0888-313-236',
           'Lecturer', $CHS, false, 'academic', null, false, 'The lecturer list printed the surname with a trailing hyphen; this sheet does not.'),
        $p('Daniel Borlon - Instructor', 'Daniel', null, 'Borlon', 'male', '0770-655-940',
           'Instructor', $CHS, false, 'academic'),
        $p('Lawolo Elvin Gibson - Lecturer', 'Lawolo', 'Elvin', 'Gibson', 'male', '0776-534-343',
           'Lecturer', $CHS, false, 'academic'),

        /* ---- College of Business ---- */
        $p('Melvin G Sackie - Instructor', 'Melvin', null, 'Sackie', 'male', '0886-465-189',
           'Instructor', $COB, false, 'academic', null, false, 'The sheet gives a middle initial "G" the lecturer list does not have.'),
        $p('Mounda A Williams - Lecturer', 'Mounda', 'A.', 'Williams', 'male', '0770-974-486',
           'Lecturer', $COB, false, 'academic'),
        $p('Abraham B Kambleh - Lecturer', 'Abraham', null, 'Kambleh', 'male', '0777-339-396',
           'Lecturer', $COB, false, 'academic', null, false, 'The sheet gives a middle initial "B" the lecturer list does not have.'),
        $p('Samuel O Osuolale - Lecturer', 'O.', 'Samuel', 'Osuolale', 'male', '0770-655-730',
           'Lecturer', $COB, false, 'academic', null, false, 'The sheet orders the names "Samuel O"; the lecturer list has "O. Samuel".'),
        $p('Nimene J Wisseh - Lecturer', 'J.', 'Nimene', 'Wisseh', 'male', '0770-596-218',
           'Lecturer', $COB, false, 'academic', null, false, 'The sheet orders the names "Nimene J"; the lecturer list has "J. Nimene".'),
        $p('James Keyan - Lecturer', 'James', null, 'Keyan', 'male', '0776-310-116',
           'Lecturer', $COB, false, 'academic'),
        $p('Moses M Kesselly - Lecturer', 'Moses', null, 'Kesselly', 'male', '0770-175-917',
           'Lecturer', $COB, false, 'academic', null, false, 'The sheet gives a middle initial "M" the lecturer list does not have.'),
        $p('Moses Olando Korpah - Lecturer', 'Moses', null, 'Korpah', 'male', '0776-792-484',
           'Lecturer', $COB, false, 'academic', null, false, 'The sheet gives a further forename, "Olando".'),

        /* ---- College of Information Technology ---- */
        $p('Christopher J Merlin - Lecturer', 'Christopher', 'J.', 'Melin', 'male', '0777-744-156',
           'Lecturer', $CIT, false, 'academic', null, false,
           'The sheet spells the surname "Merlin"; the lecturer list has "Melin". Confirm which is right.'),
        $p('Jerome N Tokpah - Lecturer', 'Jerome', null, 'Tokpa', 'male', '0776-456-762',
           'Lecturer', $CIT, false, 'academic', null, false,
           'The sheet spells the surname "Tokpah"; the lecturer list has "Tokpa".'),
        $p('Theophilius Alpha Yambasu - Lecturer', 'Theophilius', 'Alpha', 'Yambasu', 'male', '0761-439-821',
           'Lecturer', $CIT, false, 'academic'),
        $p('Alimamine S Kemokai - Lecturer', 'Alimamine', 'S.', 'Kemokai', 'male', '0777-534-633',
           'Lecturer', $CIT, false, 'academic'),
        $p('Augustine Peatan - Instructor', 'Augustine', null, 'Peatan', 'male', '0771-223-921',
           'Instructor', $CIT, false, 'academic'),
        $p('Prince N.W Droh - Lecture', 'Prince', null, 'Droh', 'male', '0770-279-095',
           'Lecturer', $CIT, false, 'academic', null, false, 'Printed "Lecture"; taken as Lecturer. The sheet adds initials "N.W".'),
        $p('Alex Charleston Duah Desoe - Lecturer', 'Alex', 'Charleston Duah', 'Desoe', 'male', '0773-125-260',
           'Lecturer', $CIT, false, 'academic'),
        $p('Jared Kollie - Lecturer', 'Jared', null, 'Kollie', 'male', '0776-154-570',
           'Lecturer', $CIT, true, 'academic', 'lecturer', true,
           'Not in the lecturer list; added from this sheet.'),
        $p('Moses G Johnson - Lecturer', 'Moses', null, 'Johnson', 'male', '0886-526-376',
           'Lecturer', $CIT, false, 'academic', null, false, 'The sheet gives a middle initial "G" the lecturer list does not have.'),

        /* ---- College of Education ---- */
        $p('Samuel G Paye - Lecturer', 'Samuel', 'G.', 'Paye', 'male', '0777-907-196',
           'Lecturer', $COE, false, 'academic'),
        $p('James Achampong - Lecturer', 'James', null, 'Achampong', 'male', null,
           'Lecturer', $COE, false, 'academic', null, false,
           'The sheet gives no telephone number for him, and spells the college "Collee of Education".'),
    ],
];
