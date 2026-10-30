<?php
/**
 * Lecturers by college, transcribed from "LECTURERS & DEPARTMENTS.pdf".
 *
 * Imported automatically on the next page load after this file changes
 * (Admin > Data Imports shows the result). By hand:  php database/data_import.php database/data/03_lecturers_bbiu.php
 * Personal data: the repository must stay private.
 *
 * The source gives each lecturer's college but no department, rank, e-mail,
 * phone or staff number. Departments can be set later under Staff > Edit.
 *
 * Each row: printed  - the name exactly as in the PDF
 *           title / first / other / last - the parsed name ("other" = middle names/initials)
 *           sex    - inferred from the title only (Mr. = male, Mrs./Ms. = female); otherwise null
 *           note   - anything about the entry the registrar should confirm
 *
 * Colleges in the PDF -> system codes:
 *   College of Liberal Arts             -> CLASS (College of Liberal Arts & Social Sciences)
 *   College of Health Sciences          -> CHS   (College of Health Science)
 *   College of Business                 -> COB
 *   College of Information Technology   -> CIT
 *   College of Education                -> COE
 */
declare(strict_types=1);

$l = static fn (string $printed, ?string $title, string $first, ?string $other, string $last, ?string $sex, ?string $note = null): array =>
    compact('printed', 'title', 'first', 'other', 'last', 'sex', 'note');

$kromah = 'Two lecturers named Anthony Kromah in Liberal Arts (middle initials D. and F.); confirm they are different people.';

return [
    'type'        => 'lecturers',
    'source'      => 'LECTURERS & DEPARTMENTS.pdf',
    'designation' => 'Lecturer',
    'colleges'    => [
        'CLASS' => [
            $l('Rev. Dr. James P. Toga', 'Rev. Dr.', 'James', 'P.', 'Toga', null),
            $l('Mr. Garmondeh Yebleh', 'Mr.', 'Garmondeh', null, 'Yebleh', 'male'),
            $l('Mr. Marcathy S. Hne', 'Mr.', 'Marcathy', 'S.', 'Hne', 'male'),
            $l('Mr. Pius Wreh…', 'Mr.', 'Pius', null, 'Wreh', 'male', 'Printed as "Wreh…": the name may be cut off.'),
            $l('Cllr. Anthony D. Kromah', 'Cllr.', 'Anthony', 'D.', 'Kromah', null, $kromah),
            $l('Mr. Tuah Saytue Sneh', 'Mr.', 'Tuah', 'Saytue', 'Sneh', 'male'),
            $l('Mr. Cooper Mykers …', 'Mr.', 'Cooper', null, 'Mykers', 'male', 'Printed as "Mykers …": the name may be cut off.'),
            $l('Mrs. Jacqueline Tuan Thomas', 'Mrs.', 'Jacqueline', 'Tuan', 'Thomas', 'female'),
            $l('Mr. Christopher Sellee', 'Mr.', 'Christopher', null, 'Sellee', 'male'),
            $l('Mr. Momodu Dukuly', 'Mr.', 'Momodu', null, 'Dukuly', 'male'),
            $l('Mr. Francis Pelena Jr.', 'Mr.', 'Francis', null, 'Pelena Jr.', 'male'),
            $l('Mr. Sayou Tequah Jr.', 'Mr.', 'Sayou', null, 'Tequah Jr.', 'male'),
            $l('Anthony F. Kromah', null, 'Anthony', 'F.', 'Kromah', null, 'Printed without a title. ' . $kromah),
            $l('Mr. Milton Yoko', 'Mr.', 'Milton', null, 'Yoko', 'male'),
        ],
        'CHS' => [
            $l('Ms. Linda R Keah.', 'Ms.', 'Linda', 'R.', 'Keah', 'female'),
            $l('Dr. Emmanuel Flomo', 'Dr.', 'Emmanuel', null, 'Flomo', null),
            $l('Dr. Emmanuel Olatunji Ogunleye', 'Dr.', 'Emmanuel', 'Olatunji', 'Ogunleye', null),
            $l('Dr G. Trokon Richards', 'Dr.', 'G.', 'Trokon', 'Richards', null),
            $l('Mr. Augustine Marwolo', 'Mr.', 'Augustine', null, 'Marwolo', 'male'),
            $l('Mr. F. Kolubah L. Wriyee', 'Mr.', 'F.', 'Kolubah L.', 'Wriyee', 'male'),
            $l('Dr. Gondah Lepkeh-', 'Dr.', 'Gondah', null, 'Lepkeh', null, 'Printed as "Lepkeh-" (trailing hyphen): the surname may be hyphenated and cut off.'),
            $l('Mr. Daniel Borlon', 'Mr.', 'Daniel', null, 'Borlon', 'male'),
            $l('Mr. Lawolo Elvin Gibson', 'Mr.', 'Lawolo', 'Elvin', 'Gibson', 'male'),
        ],
        'COB' => [
            $l('Mr. Melvin Sackie', 'Mr.', 'Melvin', null, 'Sackie', 'male'),
            $l('Mr. Mounda A. Williams', 'Mr.', 'Mounda', 'A.', 'Williams', 'male'),
            $l('Mr. Abraham Kambleh', 'Mr.', 'Abraham', null, 'Kambleh', 'male'),
            $l('Mr. O. Samuel Osuolale', 'Mr.', 'O.', 'Samuel', 'Osuolale', 'male'),
            $l('Mr. J. Nimene Wisseh', 'Mr.', 'J.', 'Nimene', 'Wisseh', 'male'),
            $l('Mr. James Keyan', 'Mr.', 'James', null, 'Keyan', 'male'),
            $l('Mr. Moses Kesselly', 'Mr.', 'Moses', null, 'Kesselly', 'male'),
            $l('Mr. Moses Korpah', 'Mr.', 'Moses', null, 'Korpah', 'male'),
            $l('Mr. Roland Oghogho', 'Mr.', 'Roland', null, 'Oghogho', 'male'),
        ],
        'CIT' => [
            $l('Mr. Christopher J. Melin', 'Mr.', 'Christopher', 'J.', 'Melin', 'male'),
            $l('Mr. Jerome Tokpa', 'Mr.', 'Jerome', null, 'Tokpa', 'male'),
            $l('Mr. Theophilius Alpha Yambasu', 'Mr.', 'Theophilius', 'Alpha', 'Yambasu', 'male'),
            $l('Mr. Alimamine S. Kemokai', 'Mr.', 'Alimamine', 'S.', 'Kemokai', 'male'),
            $l('Mr. Augustine Peatan', 'Mr.', 'Augustine', null, 'Peatan', 'male', 'The source numbers the IT list 1-4 then 6-9: no. 5 is missing; confirm no lecturer was left out.'),
            $l('Mr. Prince Droh', 'Mr.', 'Prince', null, 'Droh', 'male'),
            $l('Alex Charleston Duah Desoe', null, 'Alex', 'Charleston Duah', 'Desoe', null, 'Printed without a title.'),
            $l('Mr. Moses Johnson', 'Mr.', 'Moses', null, 'Johnson', 'male'),
        ],
        'COE' => [
            $l('Mr. Samuel G. PAYE', 'Mr.', 'Samuel', 'G.', 'Paye', 'male'),
            $l('Mr. James Achampong', 'Mr.', 'James', null, 'Achampong', 'male'),
        ],
    ],
];
