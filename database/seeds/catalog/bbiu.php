<?php
/**
 * Best Brains International University - academic catalogue.
 *
 * Transcribed from "ACADEMIC INFO FOR SERVER.pdf": colleges, departments,
 * programmes, four-year course plans, electives, minors, the grading scale,
 * academic years and semesters. Loaded by database/seeds/academic.php.
 *
 * Plan rows:
 *   $c(code, title, credits, note?)      a course
 *   $slot(label, credits, type, note?)   a placeholder (minor / elective / content area / to be confirmed)
 *   $either([...rows])                   alternatives in the same term; the student takes one
 *
 * Where the source contradicts itself the row carries a note saying what the
 * source printed, so the registrar can see and confirm every correction.
 * Codes ending in "B" or containing "TBA" are provisional: the source reused a
 * code for a different course, or printed no code at all.
 */
declare(strict_types=1);

$c = static fn (string $code, string $title, int $credits, ?string $note = null): array =>
    ['code' => $code, 'title' => $title, 'credits' => $credits, 'note' => $note];
$slot = static fn (string $label, int $credits, string $type, ?string $note = null): array =>
    ['slot' => $label, 'credits' => $credits, 'type' => $type, 'note' => $note];
$either = static fn (array $rows): array => ['either' => $rows];

$minor    = static fn (): array => $slot('Minor course', 3, 'minor');
$elective = static fn (): array => $slot('Elective', 3, 'elective');
$content  = static fn (): array => $slot('Content area course', 3, 'concentration');
$tbc      = static fn (string $note): array => $slot('Course to be confirmed', 3, 'core', $note);

/* ------------------------------------------------------------------ */
/*  Shared first-year courses                                          */
/* ------------------------------------------------------------------ */
$engl101 = $c('ENGL 101', 'Freshman English I', 3);
$engl102 = $c('ENGL 102', 'Freshman English II', 3);
$engl201 = $c('ENGL 201', 'Sophomore English I', 3);
$engl202 = $c('ENGL 202', 'Sophomore English II', 3);
$fren101 = $c('FREN 101', 'Fundamentals of French I', 3);
$fren102 = $c('FREN 102', 'Fundamentals of French II', 3);
$math101 = $c('MATH 101', 'General Mathematics I', 3);
$math102 = $c('MATH 102', 'General Mathematics II', 3);
$sosc101 = $c('SOSC 101', 'Introduction to Social Science', 3);
$sosc102 = $c('SOSC 102', 'Liberian Society', 3);
$phsc101 = $c('PHSC 101', 'Introduction to Physical Science I', 3);
$phsc102 = $c('PHSC 102', 'Introduction to Physical Science II', 3);
$cosc101 = $c('COSC 101', 'Introduction to Computer Literacy', 3);
$cosc102 = $c('COSC 102', 'Computer Applications', 3);
$phed101 = $c('PHED 101', 'Physical Education I', 1);
$phed102 = $c('PHED 102', 'Physical Education II', 1);
$phil102 = $c('PHIL 102', 'Critical Thinking', 2);
$phil201 = $c('PHIL 201', 'Introduction to Philosophy', 3);
$entr201 = $c('ENTR 201', 'Entrepreneurship Education I', 1);
$entr202 = $c('ENTR 202', 'Entrepreneurship Education II', 1);

/* College of Business first year (identical for Accounting, Management, Economics, Public Administration). */
$businessY1S1 = [
    $engl101, $c('MATH 107', 'Pre-Calculus', 4), $c('PHSC 101', 'Introduction to Physical Science I', 2), $fren101,
    $c('COSC 101', 'Introduction to Computer Literacy', 2), $phed101, $c('ACCT 101', 'Introduction to Accounting I', 3), $sosc101,
];
$businessY1S2 = [
    $engl102, $c('MATH 108', 'Mathematics for Decision Making', 4), $c('COSC 102', 'Computer Applications', 2), $fren102,
    $phed102, $c('ACCT 102', 'Introduction to Accounting II', 3), $sosc102,
];

/* Nursing years 1-4, shared by the nursing and nurse-midwifery programmes. */
$nursingPlan = [
    1 => [
        1 => [
            $engl101, $c('MATH 103', 'Basic Math', 2), $c('BIOL 101', 'General Biology I', 3, 'Source lists "General Biology" at 3 credits (4 in other programmes).'),
            $c('BIOL 106', 'Anatomy and Physiology I', 3, 'Source code "BOI 106" corrected to the BIOL prefix.'),
            $c('COSC 101', 'Introduction to Computer Literacy', 2), $c('PSYC 101', 'Introduction to Psychology', 3),
            $c('NSG 101', 'Fundamentals of Nursing I', 4),
        ],
        2 => [
            $engl102, $c('PHAM 102', 'Clinical Pharmacology I', 3), $c('BIOL 108', 'Anatomy and Physiology II', 3),
            $c('NSG 102', 'Fundamentals of Nursing II', 4), $c('COSC 102', 'Computer Applications', 2),
            $c('BIOL 105', 'Microbiology', 3, 'Source code "BIO 105" corrected to the BIOL prefix.'),
            $c('PHED 111', 'Physical Education', 1, 'Highlighted for review in the source.'),
        ],
    ],
    2 => [
        1 => [
            $c('NSG 201', 'Nutrition', 3),
            $c('NSG 203', 'Obstetrics Maternity Nursing I', 4),
            $c('NSG 201B', 'Medical-Surgical Nursing I', 4, 'Provisional code: source also prints NSG 201 for Nutrition.'),
            $c('NSG 205', 'Pediatric Nursing I', 3),
            $c('NSG 209', 'Ethical and Professional Adjustment', 2),
            $c('ENTR 201', 'Entrepreneurship Education I', 1),
            $c('ENVS 212', 'Environmental Economics', 3, 'Highlighted for review in the source.'),
        ],
        2 => [
            $c('NSG 204', 'Obstetrics Maternity Nursing II', 5),
            $c('NSG 206', 'Medical-Surgical Nursing II', 5),
            $c('ENTR 202', 'Entrepreneurship Education II', 1, 'Source prints "ENVS 208 Entrepreneurship II"; ENVS 208 is Environmental Microbiology.'),
            $c('NSG TBA1', 'Pediatric Nursing II', 4, 'Source prints "NSG" with no course number.'),
            $c('NSG TBA2', 'Primary Health Care', 4, 'Source prints "ENVS 104" (Introduction to Environmental Science); the source asks for an NSG code.'),
            $c('PHIL 102', 'Critical Thinking', 1, 'Source lists 1 credit here (2 in other programmes).'),
        ],
    ],
    3 => [
        1 => [
            $c('NSG 301', 'Tropical and Communicable Diseases', 3),
            $c('NSG 303', 'Psychiatric Mental Health Nursing I', 5, 'Highlighted for review in the source.'),
            $c('NSG 305', 'Evidence-Based Practice (EBP) in Nursing', 2),
            $c('NSG 309', 'Nursing Care of the Aging Client', 3),
            $c('NSG 311', 'Emergency and Critical Nursing', 4),
            $c('NSG 102B', 'Health Assessment', 2, 'Provisional code: source prints NSG 102, which is Fundamentals of Nursing II.'),
        ],
        2 => [
            $c('NSG 302', 'Nursing Administration', 6),
            $c('NSG 304', 'Practice Workshop', 1),
            $c('NSG TBA3', 'Psychiatric Mental Health Nursing II', 5, 'Source prints "NSG" with no course number.'),
            $c('NSG TBA4', 'Nursing Capstone Seminar', 1, 'Source prints "NSG" with no course number.'),
            $c('NSG 308', 'Simplified Diagnosis and Treatment I', 3),
            $c('PHED TBA1', 'Research Proposal', 2, 'Source prints "PHED" with no course number.'),
        ],
    ],
    4 => [
        1 => [
            $c('NSG 403', 'Nursing Capstone Clinical Affiliation', 6),
            $c('NSG 401', 'Simplified Diagnosis and Treatment II', 6, 'Source titles NSG 308 and NSG 401 identically; numbered I and II here.'),
        ],
        2 => [
            $c('PHED 402', 'Thesis Development and Defense', 6),
            $c('NSG 406', 'Nursing Leadership and Management', 6),
            $c('NSG 404', 'Nursing Research and Statistics', 3),
            $c('PHED 411', 'Teaching and Curriculum Development', 2, 'Credits blank in the source; 2 inferred from the stated semester total of 17.'),
        ],
    ],
];

return [
    /* ------------------------------------------------------------------ */
    'colleges' => [
        'CHS'   => 'College of Health Science',
        'CLASS' => 'College of Liberal Arts & Social Sciences',
        'COB'   => 'College of Business',
        'COE'   => 'College of Education',
        'CIT'   => 'College of Information Technology',
    ],

    /* code => [college, name] */
    'departments' => [
        'MCOM' => ['CLASS', 'Department of Mass Communication'],
        'SOCI' => ['CLASS', 'Department of Sociology'],
        'HIRL' => ['CLASS', 'Department of History & International Relations'],
        'SOWK' => ['CLASS', 'Department of Social Works'],
        'CRJS' => ['CLASS', 'Department of Criminal Justice'],
        'ACCT' => ['COB', 'Department of Accounting'],
        'MGMT' => ['COB', 'Department of Management'],
        'PADM' => ['COB', 'Department of Public Administration'],
        'ECON' => ['COB', 'Department of Economics'],
        'PUBH' => ['CHS', 'Department of Public Health'],
        'ENVS' => ['CHS', 'Department of Environmental Science'],
        'NURS' => ['CHS', 'Department of Nursing'],
        'MLT'  => ['CHS', 'Department of Medical Lab Technology'],
        'PRED' => ['COE', 'Department of Primary Education'],
        'SCED' => ['COE', 'Department of Secondary Education'],
        'EDAD' => ['COE', 'Department of Education Administration'],
        'GDCN' => ['COE', 'Department of Guidance & Counseling'],
        'ECED' => ['COE', 'Department of Early Childhood Education'],
        // Not in the department list, but the source has a "Department of Information Technology" curriculum.
        'IT'   => ['CIT', 'Department of Information Technology'],
        // Not in the source: owns the general-education courses every programme shares.
        'GENS' => ['CLASS', 'General Studies'],
    ],

    /* Which department owns a course, by subject prefix. Codes in course_departments override. */
    'subject_departments' => [
        'MCBJ' => 'MCOM', 'MASC' => 'MCOM', 'MCOM' => 'MCOM',
        'SOCI' => 'SOCI',
        'HIRL' => 'HIRL',
        'SOWK' => 'SOWK',
        'CRJS' => 'CRJS', 'CJS' => 'CRJS', 'ROTC' => 'CRJS',
        'ACCT' => 'ACCT',
        'MANG' => 'MGMT', 'MGT' => 'MGMT', 'MGMT' => 'MGMT',
        'PADM' => 'PADM',
        'ECON' => 'ECON',
        'PUBH' => 'PUBH', 'PHC' => 'PUBH',
        'ENVS' => 'ENVS',
        'NSG' => 'NURS', 'PHAM' => 'NURS', 'OBGY' => 'NURS',
        'EDUC' => 'SCED',
        'IT' => 'IT', 'ENT' => 'IT',
        'ENGL' => 'GENS', 'ENG' => 'GENS', 'FREN' => 'GENS', 'MATH' => 'GENS', 'SOSC' => 'GENS', 'PHSC' => 'GENS',
        'COSC' => 'GENS', 'PHED' => 'GENS', 'PHIL' => 'GENS', 'ENTR' => 'GENS', 'LEAD' => 'GENS',
        'GSC' => 'GENS', 'PSYC' => 'GENS', 'BIOL' => 'GENS', 'CHEM' => 'GENS', 'PHYS' => 'GENS', 'GED' => 'GENS',
    ],
    'course_departments' => [
        'BIOL 501' => 'NURS', 'BIOL 502' => 'NURS', 'BIOL 511' => 'NURS', 'PSYC 501' => 'NURS',
        'PHED 402' => 'NURS', 'PHED 411' => 'NURS', 'PHED TBA1' => 'NURS',
        'ENGL 306' => 'SCED', 'MATH 304' => 'SCED', 'MATH 204' => 'SCED',
    ],

    /*
     * One title per course code. Wherever the source prints the same code with
     * differently worded titles, the canonical title is fixed here; the loader
     * refuses to run if a code reaches it with two titles and no entry below.
     */
    'canonical_titles' => [
        'MATH 102' => 'General Mathematics II',
        'PHSC 101' => 'Introduction to Physical Science I',
        'BIOL 101' => 'General Biology I',
        'MANG 204' => 'Principles of Management II',
        'MANG 301' => 'Organizational Behavior',
        'MANG 305' => 'Application to Computer Technology',
        'ECON 212' => 'Introduction to Statistics',
        'ECON 313' => 'Business Statistics I',
        'ECON 314' => 'Business Statistics II',
        'HIRL 206' => 'History of Liberia: 1400 – 1900',
        'HIRL 303' => 'Liberian History: 1900 – 1990',
        'PHIL 102' => 'Critical Thinking',
        'ENGL 201' => 'Sophomore English I',
        'ENGL 202' => 'Sophomore English II',
    ],

    /* ------------------------------------------------------------------ */
    /*  Programmes                                                         */
    /* ------------------------------------------------------------------ */
    'programs' => [

        /* ===================== College of Health Science ===================== */
        'BSES' => [
            'department' => 'ENVS', 'name' => 'Bachelor of Science in Environmental Science', 'award' => 'BSc. Environmental Science',
            'level' => 'bachelor', 'years' => 4, 'credits' => 150,
            'plan' => [
                1 => [
                    1 => [$engl101, $math101, $c('BIOL 101', 'General Biology I', 4), $c('CHEM 101', 'Introduction to (Inorganic) Chemistry I', 4),
                          $c('COSC 101', 'Introduction to Computer Literacy', 2), $phed101, $c('PHYS 101', 'General Physics I', 3)],
                    2 => [$engl102, $math102, $c('BIOL 102', 'General Biology II', 4), $c('CHEM 102', 'Introduction to (Organic) Chemistry II', 4),
                          $c('COSC 102', 'Computer Applications', 2), $c('ENVS 104', 'Introduction to Environmental Science', 3), $phed102],
                ],
                2 => [
                    1 => [$c('ENVS 201', 'Introduction to Ecology', 3), $c('ENVS 203', 'Environmental Resource Management', 3), $engl201,
                          $c('ENVS 205', 'Soil Resources', 3), $c('ENVS 209', 'Map Analysis', 3), $c('ENVS 211', 'Geochemistry', 3)],
                    2 => [$c('ENVS 202', 'Global Environmental Issues', 3), $c('ENVS 204', 'Environmental Hazards and Disaster Management', 3),
                          $c('ENVS 206', 'Water Resources Management', 3), $c('ENVS 208', 'Environmental Microbiology', 3),
                          $c('ENVS 210', 'Introduction to Toxicology', 3), $c('ENVS 212', 'Environmental Economics', 3), $phil102],
                ],
                3 => [
                    1 => [$c('ENVS 301', 'Environmental Pollution', 3), $c('ENVS 303', 'Climate Change and Environment', 3),
                          $c('ENVS 305', 'Applied Climatology', 3), $c('ENVS 309', 'Air Photo Interpretation', 3),
                          $c('ENVS 311', 'Introduction to Measurement and Field Methods in Environmental Science', 2),
                          $c('ENVS 313', 'Environmental Laws and Policies', 3), $entr201],
                    2 => [$c('ENVS 302', 'Water and Waste Management', 3), $c('ENVS 304', 'Environmental Chemistry', 3),
                          $c('ENVS 306', 'Biodiversity Conservation', 3), $c('ENVS 308', 'Research Methods', 3), $c('ENVS 310', 'Remote Sensing', 3),
                          $c('ENVS 311B', 'Environmental Impact Assessment and Auditing', 2, 'Provisional code: source also prints ENVS 311 for Measurement and Field Methods.'),
                          $entr202],
                ],
                4 => [
                    1 => [$c('ENVS 401', 'Geographic Information System', 3), $c('ENVS 403', 'Environment Protection Agencies', 3),
                          $c('ENVS 405', 'Environmental Health and Safety', 3), $c('ENVS 407', 'Hydrology and Water Resources', 3),
                          $c('LEAD 407', 'Leadership and Ethics', 3), $elective()],
                    2 => [$c('ENVS 402', 'Biogeography', 3), $c('ENVS 404', 'Ecology of Natural Resources', 3),
                          $c('ENVS 406', 'Industrial Wastes and Industrial Water Treatment', 3), $c('ENVS 408', 'Thesis', 6), $elective()],
                ],
            ],
            'electives' => [
                'label' => 'Electives (choose two)',
                'courses' => [
                    $c('ENVS 318', 'Rural Development Strategies', 3, 'Credits not stated in the source; 3 assumed.'),
                    $c('ENVS 314', 'Oceanography', 3, 'Credits not stated in the source; 3 assumed.'),
                    $c('ENVS 409', 'Urban Environmental Management', 3, 'Credits not stated in the source; 3 assumed.'),
                    $c('ENVS 411', 'Rural Development Strategies', 3, 'Same title as ENVS 318 in the source; confirm which is intended.'),
                    $c('ENVS 414', 'Population, Environment and Development', 3, 'Credits not stated in the source; 3 assumed.'),
                    $c('ENVS 421', 'Elements of Land Surveying', 3, 'Credits not stated in the source; 3 assumed.'),
                ],
            ],
        ],

        'BSPH' => [
            'department' => 'PUBH', 'name' => 'Bachelor of Science in Public Health', 'award' => 'BSc. Public Health',
            'level' => 'bachelor', 'years' => 4, 'credits' => 150,
            'plan' => [
                1 => [
                    1 => [$engl101, $math101, $c('BIOL 101', 'General Biology I', 4), $c('CHEM 101', 'Introduction to (Inorganic) Chemistry I', 4),
                          $cosc101, $phed101, $c('PHYS 101', 'General Physics I', 3)],
                    2 => [$engl102, $math102, $c('BIOL 102', 'General Biology II', 4), $c('CHEM 102', 'Introduction to (Organic) Chemistry II', 4),
                          $cosc102, $c('PUBH 104', 'Introduction to Public Health', 3), $phed102],
                ],
                2 => [
                    1 => [$c('PSYC 201', 'Introduction to Psychology', 3), $engl201, $c('SOCI 201', 'Introduction to Sociology', 3),
                          $c('PUBH 201', 'Introduction to Physiology', 3), $c('PUBH 203', 'Principles of Epidemiology I', 3),
                          $c('PUBH 205', 'Biochemistry', 3), $entr201],
                    2 => [$c('SOSC 102', 'Liberian Society', 3, 'Source prints "SOCI 102".'),
                          $c('ENGL 202', 'Sophomore English II', 3, 'Source prints "ENGL 201 Sophomore English II".'),
                          $c('PUBH 202', 'Biostatistics', 3), $c('PUBH 208', 'Public Health Services', 3), $c('PUBH 206', 'Human Genetics', 3),
                          $c('PUBH 204', 'Principles of Epidemiology II', 3, 'Source prints "PSYC 204".'), $entr202, $phil102],
                ],
                3 => [
                    1 => [$c('PUBH 301', 'Environmental Health', 3), $c('PUBH 303', 'Family and Reproductive Health', 3),
                          $c('PUBH 305', 'Introduction to Primary Health Care', 3), $c('PUBH 307', 'Public Health Laws', 3),
                          $c('PUBH 309', 'Human Diseases and Prevention', 3), $c('PUBH 311', 'Community Health Practices', 3)],
                    2 => [$c('PUBH 302', 'Public Health Nutrition', 3), $c('PUBH 304', 'Disaster and Emergency Management', 3),
                          $tbc('Source has a 3-credit row with no code or title.'),
                          $c('PUBH 308B', 'Public and Global Health', 3, 'Provisional code: source also prints PUBH 308 for Research Methods.'),
                          $c('PUBH 310', 'Personal Health and Wellness', 3), $c('PUBH 308', 'Research Methods', 3)],
                ],
                4 => [
                    1 => [$c('PUBH 401', 'Health Policy and Finance', 3), $c('PUBH 403', 'Principles of Pharmacology', 3),
                          $c('PUBH 405', 'Planning Public Health Programs', 3), $c('PUBH 407', 'Contemporary Issues in Public Health', 3),
                          $c('PUBH 409', 'Human Health and Climate Change', 3), $c('LEAD 407', 'Leadership and Ethics', 3)],
                    2 => [$c('PUBH 402', 'Internship', 6), $c('PUBH 406', 'Evaluating Public Health Program', 2), $c('PUBH 408', 'Thesis / Project', 6)],
                ],
            ],
        ],

        'BSN' => [
            'department' => 'NURS', 'name' => 'Bachelor of Science in Nursing', 'award' => 'BSc. Nursing',
            'level' => 'bachelor', 'years' => 4, 'credits' => null,
            'description' => 'Registered nursing programme. Year 2 Semester 2 is the first clinical semester; Year 3 Semester 2 is capping and badging; Year 4 Semester 1 is the clinical affiliation; Year 4 Semester 2 is the thesis defense.',
            'plan' => $nursingPlan,
        ],

        'BSNMW' => [
            'department' => 'NURS', 'name' => 'Bachelor of Science in Nursing (Nurse-Midwifery)', 'award' => 'BSc. Nursing (Nurse-Midwifery)',
            'level' => 'bachelor', 'years' => 5, 'credits' => null,
            'description' => 'Ten-semester nurse-midwifery track: the four-year nursing curriculum followed by the Year 5 midwifery semesters (semesters 9 and 10).',
            'plan' => $nursingPlan + [
                5 => [
                    1 => [
                        $c('BIOL 501', 'Anatomy of the Reproductive System', 5, 'Source code "BIO 501" corrected to the BIOL prefix.'),
                        $c('BIOL 502', 'Pathophysiology of Reproduction', 3, 'Source code "BIO 502" corrected to the BIOL prefix.'),
                        $c('PHC 501', 'Health Statistics', 3),
                        $c('OBGY 502', 'Midwifery Administration: Management and Leadership', 4),
                        $c('PHAM 501', 'Advanced Pharmacology', 3),
                        $c('OBGY TBA1', 'Teaching Methodology', 3, 'Source prints no course code. Semester listed as 17 credits, but its rows add up to 21.'),
                    ],
                    2 => [
                        $c('BIOL 511', 'Embryology & Genetics', 3, 'Source code "BIO 511" corrected to the BIOL prefix.'),
                        $c('OBGY 510', 'Professional Issues in Midwifery', 3),
                        $c('OBGY TBA2', 'Obstetric Emergencies', 6, 'Source prints "OBGY" with no course number.'),
                        $c('PSYC 501', 'Developmental Psychology', 2),
                        $c('OBGY 513', 'Senior Seminar/Practicum', 2),
                    ],
                ],
            ],
        ],

        'BSMLS' => [
            'department' => 'MLT', 'name' => 'Bachelor of Science in Medical Laboratory Science', 'award' => 'BSc Medical Laboratory Science',
            'level' => 'bachelor', 'years' => 4, 'credits' => null, 'plan' => [],
        ],

        /* ============== College of Liberal Arts & Social Sciences ============== */
        'BASOC' => [
            'department' => 'SOCI', 'name' => 'Bachelor of Arts in Sociology', 'award' => 'BA Sociology',
            'level' => 'bachelor', 'years' => 4, 'credits' => 147,
            'plan' => [
                1 => [
                    1 => [$engl101, $fren101, $math101, $sosc101, $phsc101, $cosc101, $c('PHED 101', 'Physical Education I', 1, 'Source prints "PHEP 101".')],
                    2 => [$engl102, $fren102, $math102, $sosc102, $phsc102, $cosc102, $phed102, $phil102],
                ],
                2 => [
                    1 => [$engl201, $phil201, $c('SOCI 201', 'Introduction to Sociology', 3), $c('SOCI 203', 'Marriage & Family', 3),
                          $c('SOCI 205', 'Social Institutions', 3), $c('PSYC 201', 'Introduction to Psychology', 3), $entr201],
                    2 => [$engl202, $c('SOCI 202', 'Social Problems and Social Policy', 3), $c('SOCI 204', 'Social Organizations', 3),
                          $c('PHIL 202', 'Ethics & Reasoning', 3), $minor(), $minor(), $entr202],
                ],
                3 => [
                    1 => [$c('SOCI 301', 'Urban & Industrial Sociology', 3), $c('SOCI 303', 'Collective Behavior', 3),
                          $c('SOCI 305', 'Social Stratification', 3), $c('SOCI 307', 'Community Development', 3), $minor(), $minor()],
                    2 => [$c('SOCI 302', 'Sociology of Crime and Delinquency', 3), $c('SOCI 304', 'Gender and Social Change', 3),
                          $c('SOCI 306', 'Social Psychology', 3), $c('SOCI 310', 'Rural Sociology', 3), $minor(), $c('SOCI 308', 'Research Methods', 3)],
                ],
                4 => [
                    1 => [$c('SOCI 401', 'Social Theory', 3), $c('SOCI 403', 'Counseling Theories', 3), $c('SOCI 405', 'Deviance & Control', 3),
                          $c('SOCI 409', 'Trauma Awareness & Counseling', 3), $minor(), $c('LEAD 417', 'Leadership and Ethics', 3)],
                    2 => [$c('SOCI 404', 'Sociology of Development', 3), $c('SOCI 406', 'Social Population', 3), $c('SOCI 408', 'Thesis', 6), $minor()],
                ],
            ],
            'minor' => [
                'label' => 'Minor in Sociology (for non-Sociology majors)',
                'courses' => [
                    $c('SOCI 201', 'Introduction to Sociology', 3, 'Source lists the minor as "SOCS 201".'),
                    $c('SOCI 202', 'Social Problems and Social Policy', 3, 'Source lists the minor as "SOCS 202".'),
                    $c('SOCI 203', 'Marriage & Family', 3, 'Source lists the minor as "SOCS 203".'),
                    $c('SOCI 301', 'Urban & Industrial Sociology', 3, 'Source lists the minor as "SOCS 301".'),
                    $c('SOCI 302', 'Sociology of Crime and Delinquency', 3, 'Source lists the minor as "SOCS 309".'),
                    $either([
                        $c('SOCI 401', 'Social Theory', 3, 'Source lists the minor as "SOCS 401".'),
                        $c('SOCI 406', 'Social Population', 3, 'Source lists the minor as "SOCS 407".'),
                    ]),
                ],
            ],
        ],

        'BAMC' => [
            'department' => 'MCOM', 'name' => 'Bachelor of Arts in Mass Communication (Broadcast Journalism)', 'award' => 'BA Mass Communication',
            'level' => 'bachelor', 'years' => 4, 'credits' => 147,
            'plan' => [
                1 => [
                    1 => [$engl101, $fren101, $math101, $sosc101, $phsc101, $cosc101, $phed101],
                    2 => [$engl102, $fren102, $c('MATH 102', 'General Mathematics II', 3, 'Source titles MATH 102 "General Mathematics I".'),
                          $sosc102, $cosc102, $c('MASC 101', 'Introduction to Mass Media', 3), $phed102, $phil102],
                ],
                2 => [
                    1 => [$engl201, $c('MCBJ 201', 'Introduction to Broadcast Journalism', 3),
                          $c('PHIL 201', 'Introduction to Philosophy', 3, 'Source prints "PYSC 201".'),
                          $c('MCBJ 205', 'Principles of Radio Journalism', 3), $c('MCBJ 207', 'Principles of Television Journalism', 3),
                          $c('MCBJ 209', 'Principles and Practices of Interviewing', 3), $entr201],
                    2 => [$engl202, $c('MCBJ 202', 'Broadcast News Reporting and Writing', 3), $c('MCBJ 204', 'Radio Format', 3),
                          $c('MCBJ 206', 'The Radio Newsroom', 3), $c('PHIL 202', 'Ethics & Reasoning', 3), $minor(), $entr202],
                ],
                3 => [
                    1 => [$c('MCBJ 301', 'Media History of Liberia', 3), $c('MCBJ 303', 'Television Formats', 3),
                          $c('MCBJ 305', 'The Television Newsroom', 3), $c('MANG 301', 'Organizational Behavior', 3), $minor(), $minor()],
                    2 => [$c('MCBJ 302', 'Media Laws and Ethics', 3), $c('MCBJ 304', 'Media Management', 3),
                          $c('MCBJ 306', 'Broadcast Journalism Practicum', 3), $c('MCBJ 308', 'Research Methods', 3),
                          $c('PSYC 302', 'Social Psychology', 3), $minor()],
                ],
                4 => [
                    1 => [$c('MCBJ 401', 'Introduction to Multimedia Communication', 3), $c('MCBJ 403', 'Rhetoric and Broadcast Communication', 3),
                          $c('MCBJ 405', 'Comparative Media Systems', 3), $c('MCOM 401', 'Principles of Public Relations', 3),
                          $c('LEAD 407', 'Leadership and Ethics', 3), $minor()],
                    2 => [$c('MCOM 402', 'Broadcast Internship', 6), $c('MCOM 408', 'Thesis Writing', 6), $minor()],
                ],
            ],
            'minor' => [
                'label' => 'Minor in Mass Communication (Broadcast Journalism)',
                'courses' => [
                    $c('MCBJ 201', 'Introduction to Broadcast Journalism', 3),
                    $c('MCBJ 202', 'Broadcast News Reporting and Writing', 3),
                    $c('MCBJ 206', 'The Radio Newsroom', 3),
                    $c('MCBJ 302', 'Media Laws and Ethics', 3),
                    $c('MCBJ 305', 'The Television Newsroom', 3),
                    $c('MCBJ 401', 'Introduction to Multimedia Communication', 3),
                ],
            ],
        ],

        'BAHIR' => [
            'department' => 'HIRL', 'name' => 'Bachelor of Arts in History and International Relations', 'award' => 'BA History & International Relations',
            'level' => 'bachelor', 'years' => 4, 'credits' => 147,
            'plan' => [
                1 => [
                    1 => [$math101, $engl101, $c('SOSC 101', 'Introduction to Social Science', 3, 'Source prints "SOCS 101".'), $cosc101, $fren101,
                          $c('PHSC 101', 'Introduction to Physical Science I', 3, 'Source prints "PHYS 101 Principles of Physical Science I"; PHYS 101 is General Physics I.'),
                          $phed101],
                    2 => [$math102, $engl102, $c('SOSC 102', 'Liberian Society', 3, 'Source prints "SOCS 102".'), $cosc102, $fren102,
                          $c('PHED 102', 'Physical Education II', 1, 'Source prints "PHED 101 Physical Education II".'), $phil102],
                ],
                2 => [
                    1 => [$engl201, $c('HIRL 201', 'Introduction to International Relations', 3),
                          $c('HIRL 205', 'Africa and European Imperialism in the 19th & 20th Centuries', 3),
                          $c('HIRL 231', 'Europe up to the French Revolution', 3), $phil201, $minor(), $entr201],
                    2 => [$engl202, $c('HIRL 206', 'History of Liberia: 1400 – 1900', 3),
                          $c('HIRL 204', 'Europe from the French Revolution up to the 2nd World War', 3),
                          $c('HIRL 244', 'American History: A Survey', 3), $c('PHIL 204', 'Ethics', 3), $minor(), $entr202],
                ],
                3 => [
                    1 => [$c('HIRL 301', 'Evolution of the International System Since 1815', 3), $c('HIRL 303', 'Liberian History: 1900 – 1990', 3),
                          $c('HIRL 305', 'Issues in International Relations I', 3),
                          $c('HIRL 307', 'Liberia Foreign Policy and Diplomatic Engagements (19th to 20th Century)', 3),
                          $c('FREN 201', 'Advanced French I', 3), $minor()],
                    2 => [$c('HIRL 302', 'Historiography', 3), $c('HIRL 304', 'Economic History of Liberia (1400 – 2003)', 3),
                          $c('HIRL 306', 'Issues in International Relations II', 3), $c('FREN 202', 'Advanced French II', 3),
                          $minor(), $elective(), $c('HIRL 308', 'Research Methodology', 3)],
                ],
                4 => [
                    1 => [$c('HIRL 401', 'Diplomatic History of Liberia', 3), $c('HIRL 403', 'Liberian Historiography', 3),
                          $c('HIRL 405', 'Global/Continental/Commonwealth Organizations', 3), $minor(), $elective(),
                          $c('HIRL 409', 'Leadership and Ethics', 3)],
                    2 => [$c('HIRL 406', 'Concept and Theories of Cultural Diplomacy', 3),
                          $c('HIRL 408', 'Terrorism, Counter-Terrorism and Global Security', 3),
                          $c('HIRL 424', 'Thesis / Research Project', 6), $elective()],
                ],
            ],
            'electives' => [
                'label' => 'Electives (choose three)',
                'courses' => [
                    $c('HIRL 245', 'African Diaspora', 3),
                    $c('HIRL 310', 'International Political Economy', 3),
                    $c('HIRL 309', 'Women and Power in African History', 3),
                    $c('HIRL 315', 'State and Non-State Actors in International Politics', 3),
                    $c('HIRL 405B', 'Problems and Prospects of Regional Integration in Africa', 3, 'Provisional code: source also prints HIRL 405 for Global/Continental/Commonwealth Organizations (Year 4 core).'),
                    $c('HIRL 414', 'Developmental Issues in Africa', 3),
                ],
            ],
            'minor' => [
                'label' => 'Minor in International Relations (for non-International Relations majors)',
                'courses' => [
                    $c('HIRL 201', 'Introduction to International Relations', 3),
                    $c('HIRL 301', 'Evolution of the International System Since 1815', 3),
                    $c('HIRL 305', 'Issues in International Relations I', 3),
                    $c('HIRL 306', 'Issues in International Relations II', 3),
                    $c('HIRL 401', 'Diplomatic History of Liberia', 3),
                    $either([
                        $c('HIRL 406', 'Concept and Theories of Cultural Diplomacy', 3),
                        $slot('Any other 400-level International Relations course', 3, 'minor'),
                    ]),
                ],
            ],
        ],

        'BASW' => [
            'department' => 'SOWK', 'name' => 'Bachelor of Arts in Social Work', 'award' => 'BA Social Work',
            'level' => 'bachelor', 'years' => 4, 'credits' => 145,
            'plan' => [
                1 => [
                    1 => [$engl101, $fren101, $math101, $sosc101, $phsc101, $cosc101, $c('PHED 101', 'Physical Education I', 1, 'Source prints "PHEP 101".')],
                    2 => [$engl102, $fren102, $math102, $sosc102, $phsc102, $cosc102, $c('PHED 102', 'Physical Education II', 1, 'Source prints "PHEP 102".')],
                ],
                2 => [
                    1 => [$engl201, $phil201, $c('SOWK 201', 'Introduction to Social Work I', 3), $c('SOWK 203', 'Social Psychology', 3),
                          $c('SOWK 205', 'Social Institutions', 3), $minor(),
                          $c('ENTR 201', 'Entrepreneurship Education I', 1, 'Source prints "ENTR 202 Entrepreneurship Education I".')],
                    2 => [$engl202, $c('SOWK 208', 'Social Problems and Social Policy', 3), $c('SOWK 209', 'Sociology of Crime and Delinquency', 3),
                          $c('SOWK 305', 'Gender and Social Change', 3, 'Source also schedules SOWK 305 in Year 3 Semester 2; confirm which semester.'),
                          $c('SOWK 202', 'Introduction to Social Work II', 3), $minor(), $entr202],
                ],
                3 => [
                    1 => [$c('SOWK 301', 'Marriage and the Family', 3), $c('SOWK 309', 'Social Movement and Collective Behavior', 3),
                          $c('SOWK 303', 'Social Stratification', 3), $minor(), $minor(), $elective()],
                    2 => [$c('SOWK 308', 'Research Methods', 3), $c('SOWK 307', 'Urban Sociology', 3),
                          $c('SOWK 305', 'Gender and Social Change', 3, 'Source also schedules SOWK 305 in Year 2 Semester 2; confirm which semester.'),
                          $c('SOWK 308B', 'Community Development Studies', 3, 'Provisional code: source also prints SOWK 308 for Research Methods.'),
                          $minor(), $elective()],
                ],
                4 => [
                    1 => [$c('SOWK 401', 'Organization/Community Project Writing', 3), $c('SOWK 405', 'Classical Sociological Theory', 3),
                          $minor(), $minor(), $elective(), $c('LEAD 417', 'Leadership and Ethics', 3)],
                    2 => [$c('SOWK 412', 'Field Training / Internship', 6), $c('SOWK 410', 'Thesis', 6)],
                ],
            ],
            'minor' => [
                'label' => 'Minor in Social Work (for non-Social Work majors)',
                'courses' => [
                    $c('SOWK 201', 'Introduction to Social Work I', 3),
                    $c('SOWK 202', 'Introduction to Social Work II', 3),
                    $c('SOWK 205', 'Social Institutions', 3),
                    $c('SOWK 208', 'Social Problems and Social Policy', 3, 'Source lists the minor as "SOWK 209"; SOWK 209 is Sociology of Crime and Delinquency.'),
                    $c('SOWK 309', 'Social Movement and Collective Behavior', 3),
                    $c('SOWK 307', 'Urban Sociology', 3),
                    $c('SOWK 405', 'Classical Sociological Theory', 3),
                ],
            ],
        ],

        'BACJ' => [
            'department' => 'CRJS', 'name' => 'Bachelor of Arts in Criminal Justice', 'award' => 'BA Criminal Justice',
            'level' => 'bachelor', 'years' => 4, 'credits' => 146,
            'plan' => [
                1 => [
                    1 => [$math101, $engl101, $c('SOSC 101', 'Introduction to Social Science', 3, 'Source prints "SOCS 101".'), $cosc101,
                          $c('CRJS 101', 'Introduction to Law Enforcement', 3),
                          $c('PHSC 101', 'Introduction to Physical Science I', 3, 'Source prints "PHYS 101 Principles of Physical Science I"; PHYS 101 is General Physics I.'),
                          $c('ROTC 101', 'Reserve Officer Training Course I', 1)],
                    2 => [$math102, $engl102, $c('SOSC 102', 'Liberian Society', 3, 'Source prints "SOCS 102".'), $cosc102,
                          $c('CJS 102', 'Introduction to the Criminal Justice System', 3), $c('PSYC 201', 'Introduction to Psychology', 3),
                          $c('ROTC 102', 'Reserve Officer Training Course II', 1), $phil102],
                ],
                2 => [
                    1 => [$engl201,
                          $either([$c('GED 201', 'Fundamentals of Geography I', 3), $c('SOCI 201', 'Introduction to Sociology', 3)]),
                          $fren101, $c('CRJS 205', 'Introduction to Criminology', 3), $c('CRJS 208', 'Introduction to the Corrections', 3),
                          $phil201, $c('ENTR 201', 'Entrepreneurship Education I', 1, 'Source prints "ENTE 201".')],
                    2 => [$engl202,
                          $either([$c('GED 202', 'Fundamentals of Geography II', 3),
                                   $c('SOCI 202', 'Social Problems and Social Policy', 3, 'Source prints "SOCS 208".')]),
                          $c('CRJS 303', 'Introduction to Correctional Treatment/Rehabilitation', 3), $c('CJS 203', 'Juvenile Deviancy', 3),
                          $fren102, $c('PHIL 204', 'Ethics', 3, 'Source prints "PHIL 201 Ethics"; PHIL 201 is Introduction to Philosophy (Year 2 Semester 1).'),
                          $c('ENTR 202', 'Entrepreneurship Education II', 1, 'Source prints "ENT 202".')],
                ],
                3 => [
                    1 => [$c('CRJS 301', 'Substance Abuse Prevention', 3),
                          $c('PSYC 201', 'Introduction to Psychology', 3, 'Source also schedules PSYC 201 in Year 1 Semester 2; confirm.'),
                          $c('CRJS 307', 'Criminal Procedure Law', 3), $c('FREN 201', 'Advanced French I', 3),
                          $c('HIRL 206', 'History of Liberia: 1400 – 1900', 3, 'Source titles this "Liberian History: 1400 – 1990".'), $minor()],
                    2 => [$c('CJS 303', 'Criminalistics', 3), $c('PSYC 302', 'Social Psychology', 3), $c('FREN 202', 'Advanced French II', 3),
                          $c('HIRL 303', 'Liberian History: 1900 – 1990', 3), $minor(), $c('CRJS 306', 'Research Methodology', 3)],
                ],
                4 => [
                    1 => [$c('CJS 401', 'Criminal Investigation', 3), $c('CJS 405', 'Comparative Criminal Justice System', 3),
                          $c('PSYC 403', 'Counseling Theories', 3), $minor(), $minor(),
                          $c('LEAD 407', 'Leadership and Ethics', 2, 'Source lists 2 credits here (3 in other programmes).')],
                    2 => [$c('CJS 402', 'Private Security Management', 3), $c('CJS 424', 'Thesis', 6), $minor(), $minor()],
                ],
            ],
            'minor' => [
                'label' => 'Minor in Criminal Justice (for non-Criminal Justice majors)',
                'courses' => [
                    $c('CRJS 101', 'Introduction to Law Enforcement', 3),
                    $c('CRJS 205', 'Introduction to Criminology', 3, 'Source lists the minor as "CRJS 102".'),
                    $c('CRJS 208', 'Introduction to the Corrections', 3, 'Source lists the minor as "CRJS 201 Introduction to Correctional Institutions".'),
                    $c('CJS 203', 'Juvenile Deviancy', 3, 'Source lists the minor as "CRJS 203".'),
                    $c('CRJS 301', 'Substance Abuse Prevention', 3),
                    $c('CRJS 307', 'Criminal Procedure Law', 3),
                ],
            ],
        ],

        /* ========================= College of Business ========================= */
        'BSACC' => [
            'department' => 'ACCT', 'name' => 'Bachelor of Science in Accounting', 'award' => 'BSc. Accounting',
            'level' => 'bachelor', 'years' => 4, 'credits' => 150,
            'description' => 'The programme list names this BSc. Accounting; the curriculum heading reads "BBA in Accounting".',
            'plan' => [
                1 => [
                    1 => $businessY1S1,
                    2 => array_merge($businessY1S2, [$phil102]),
                ],
                2 => [
                    1 => [$c('ACCT 201', 'Principles of Accounting I', 3), $engl201, $c('MATH 203', 'Calculus for Non-Science Majors', 4),
                          $c('ECON 203', 'Principles of Economics I', 3), $c('MGT 201', 'Introduction to Management', 3), $entr201],
                    2 => [$engl202, $c('ACCT 202', 'Principles of Accounting II', 3), $c('ECON 204', 'Principles of Economics II', 3),
                          $c('ECON 212', 'Introduction to Statistics', 3), $entr202, $minor(), $minor()],
                ],
                3 => [
                    1 => [$c('MANG 305', 'Application to Computer Technology', 3, 'Source titles MANG 305 "Introduction to Database" here.'),
                          $c('ECON 305', 'Statistics for Economics I', 3), $c('ACCT 301', 'Intermediate Accounting I', 3),
                          $c('ACCT 303', 'Cost Accounting I', 3), $minor(), $minor()],
                    2 => [$c('ACCT 304', 'Cost Accounting II', 3), $c('ACCT 310', 'Managerial Accounting', 3),
                          $c('ECON 306', 'Statistics for Economics II', 3), $c('ACCT 302', 'Intermediate Accounting II', 3),
                          $c('ACCT 308', 'Research Methods', 3), $minor()],
                ],
                4 => [
                    1 => [$c('ACCT 401', 'Advanced Accounting I', 3), $c('ACCT 403', 'Accounting Systems and Controls', 3),
                          $c('ACCT 405', 'Auditing/Internal Audit I', 3), $c('ACCT 407', 'Elements of Taxation I', 3),
                          $minor(), $c('LEAD 418', 'Leadership and Ethics', 3)],
                    2 => [$c('ACCT 402', 'Advanced Accounting II', 3), $c('ACCT 404', 'Budget and Accounting System', 3),
                          $c('ACCT 406', 'Auditing II', 3), $c('ACCT 408', 'Thesis', 6), $c('ACCT 410', 'Elements of Taxation II', 3)],
                ],
            ],
            'minor' => [
                'label' => 'Minor in Accounting (for non-Accounting majors)',
                'courses' => [
                    $c('ACCT 201', 'Principles of Accounting I', 3),
                    $c('ACCT 202', 'Principles of Accounting II', 3),
                    $c('ACCT 303', 'Cost Accounting I', 3),
                    $c('ACCT 304', 'Cost Accounting II', 3),
                    $c('ACCT 301', 'Intermediate Accounting I', 3, 'Source lists the minor as "ACCT 307".'),
                    $c('ACCT 302', 'Intermediate Accounting II', 3, 'Source lists the minor as "ACCT 308"; ACCT 308 is Research Methods.'),
                    $c('ACCT 403', 'Accounting Systems and Controls', 3),
                ],
            ],
        ],

        'BBAMGT' => [
            'department' => 'MGMT', 'name' => 'Bachelor of Business Administration in Management', 'award' => 'BBA Management',
            'level' => 'bachelor', 'years' => 4, 'credits' => 148,
            'plan' => [
                1 => [1 => $businessY1S1, 2 => $businessY1S2],
                2 => [
                    1 => [$c('ACCT 201', 'Principles of Accounting I', 3), $c('MANG 203', 'Principles of Management I', 3), $engl201,
                          $c('ECON 203', 'Principles of Economics I', 3), $c('MATH 203', 'Calculus for Non-Science Majors', 4), $entr201],
                    2 => [$c('MANG 204', 'Principles of Management II', 3), $c('ACCT 202', 'Principles of Accounting II', 3),
                          $c('ECON 204', 'Principles of Economics II', 3), $engl202, $minor(), $minor(),
                          $c('ENTR 202', 'Entrepreneurship Education II', 1, 'Source prints "ENTR 201 Entrepreneurship Education II". Source states 17 credits for this semester; its rows add up to 19.')],
                ],
                3 => [
                    1 => [$c('MANG 301', 'Organizational Behavior', 3), $c('MANG 305', 'Application to Computer Technology', 3),
                          $c('MANG 307', 'Principles of Business Law I', 3),
                          $c('ECON 313', 'Business Statistics I', 3, 'Source titles ECON 313 "Statistics for Economics I" here.'), $minor(), $minor()],
                    2 => [$c('MANG 310', 'Principles of Business Law II', 3), $c('MANG 314', 'Human Resource Development', 3),
                          $c('MANG 318', 'Marketing Management', 3),
                          $c('ECON 314B', 'Financial Management', 3, 'Provisional code: source prints ECON 314, which is Business Statistics II.'),
                          $c('ECON 314', 'Business Statistics II', 3, 'Source prints "MANG 314 Statistics for Economics II"; MANG 314 is Human Resource Development.'),
                          $c('MANG 308', 'Research Methods', 3)],
                ],
                4 => [
                    1 => [$c('MANG 407', 'Production Operation Management', 3), $c('MANG 403', 'Quantitative Method in Management', 3),
                          $c('MANG 405', 'Small Business Management', 3), $minor(), $minor(), $elective()],
                    2 => [$c('MANG 402', 'Business Policy and Strategy', 3), $c('GSC 418', 'Leadership and Ethics', 3),
                          $c('MANG 406', 'Thesis', 6), $minor(), $elective()],
                ],
            ],
            'minor' => [
                'label' => 'Minor in Management (for non-Management majors)',
                'courses' => [
                    $c('MANG 203', 'Principles of Management I', 3, 'Source lists the minor as "MGMT 201".'),
                    $c('MANG 204', 'Principles of Management II', 3, 'Source lists the minor as "MGMT 202".'),
                    $c('MANG 301', 'Organizational Behavior', 3, 'Source lists the minor as "MGMT 301".'),
                    $c('MANG 314', 'Human Resource Development', 3, 'Source lists the minor as "MGMT 310 Human Resource Management".'),
                    $c('MANG 318', 'Marketing Management', 3, 'Source lists the minor as "MGMT 314".'),
                    $slot('Any 400-level Management course', 3, 'minor'),
                    $slot('Any 400-level Management course', 3, 'minor'),
                ],
            ],
        ],

        'BSECON' => [
            'department' => 'ECON', 'name' => 'Bachelor of Science in Economics', 'award' => 'BSc. Economics',
            'level' => 'bachelor', 'years' => 4, 'credits' => 150,
            'plan' => [
                1 => [1 => $businessY1S1, 2 => array_merge($businessY1S2, [$phil102])],
                2 => [
                    1 => [$c('ACCT 201', 'Principles of Accounting I', 3), $c('ECON 203', 'Principles of Economics I', 3), $engl201,
                          $c('MATH 203', 'Calculus for Non-Science Majors', 4), $minor(), $entr201],
                    2 => [$c('ENGL 202', 'Sophomore English II', 3, 'Source titles ENGL 202 "Advanced Composition II" here.'),
                          $c('ACCT 202', 'Principles of Accounting II', 3), $c('ECON 204', 'Principles of Economics II', 3),
                          $c('ECON 212', 'Introduction to Statistics', 3, 'Source titles ECON 212 "Statistics for Non-Business Majors" here.'),
                          $c('MANG 204', 'Principles of Management II', 3, 'Source titles MANG 204 "Principles of Management" here.'), $minor(), $entr202],
                ],
                3 => [
                    1 => [$c('ECON 301', 'Intermediate Microeconomics Analysis I', 3), $c('ECON 303', 'Intermediate Macroeconomics Analysis I', 3),
                          $c('ECON 313', 'Business Statistics I', 3), $c('ECON 319', 'Mathematics for Economics I', 3), $minor(), $minor()],
                    2 => [$c('ECON 302', 'Intermediate Microeconomics Analysis II', 3), $c('ECON 304', 'Intermediate Macroeconomics Analysis II', 3),
                          $c('ECON 314', 'Business Statistics II', 3), $c('ECON 312', 'Survey of Liberian Economy', 3),
                          $c('ECON 308', 'Research Methods', 3), $minor()],
                ],
                4 => [
                    1 => [$c('ECON 401', 'Economic Development I', 3), $c('ECON 403', 'Introduction to Econometrics', 3),
                          $c('ECON 405', 'Money and Banking', 3), $c('ECON 407', 'History of Economic Thought', 3), $minor(), $elective()],
                    2 => [$c('ECON 402', 'Economic Development II', 3), $c('ECON 404', 'Public Finance', 3), $c('LEAD 418', 'Leadership and Ethics', 3),
                          $c('ECON 406', 'Thesis', 6), $elective()],
                ],
            ],
            'electives' => [
                'label' => 'Electives',
                'courses' => [
                    $c('ECON 404B', 'Planning and Evaluation', 3, 'Provisional code: source also prints ECON 404 for Public Finance (Year 4 core). Credits not stated; 3 assumed.'),
                    $c('ECON 410', 'Managerial Economics', 3, 'Credits not stated in the source; 3 assumed.'),
                    $c('ECON 421', 'International Economics', 3, 'Credits not stated in the source; 3 assumed.'),
                ],
            ],
            'minor' => [
                'label' => 'Minor in Economics (for non-Economics majors)',
                'courses' => [
                    $c('ECON 301', 'Intermediate Microeconomics Analysis I', 3, 'Source lists the minor as "ECON 303 Intermediate Microeconomics I"; ECON 303 is Macroeconomics I.'),
                    $c('ECON 302', 'Intermediate Microeconomics Analysis II', 3, 'Source lists the minor as "ECON 304 Intermediate Microeconomics II"; ECON 304 is Macroeconomics II.'),
                    $c('ECON 303', 'Intermediate Macroeconomics Analysis I', 3, 'Source lists the minor as "ECON 309".'),
                    $c('ECON 304', 'Intermediate Macroeconomics Analysis II', 3, 'Source lists the minor as "ECON 310".'),
                    $slot('Any 400-level Economics course', 3, 'minor'),
                    $slot('Any 400-level Economics course', 3, 'minor'),
                ],
            ],
        ],

        'BBAPA' => [
            'department' => 'PADM', 'name' => 'Bachelor of Business Administration in Public Administration', 'award' => 'BBA Public Administration',
            'level' => 'bachelor', 'years' => 4, 'credits' => 148,
            'description' => 'The programme list names this BBA Public Administration; the curriculum heading reads "BSc in Public Administration".',
            'plan' => [
                1 => [
                    1 => $businessY1S1,
                    2 => array_merge($businessY1S2, [$c('PHIL 102', 'Critical Thinking', 2, 'Source states 19 credits for this semester; its rows add up to 21.')]),
                ],
                2 => [
                    1 => [$c('ACCT 201', 'Principles of Accounting I', 3), $c('PADM 201', 'Introduction to Public Administration I', 3), $engl201,
                          $c('ECON 203', 'Principles of Economics I', 3), $c('MATH 203', 'Calculus for Non-Science Majors', 4), $entr201],
                    2 => [$c('PADM 202', 'Introduction to Public Administration II', 3), $engl202, $c('ECON 204', 'Principles of Economics II', 3),
                          $c('ACCT 202', 'Principles of Accounting II', 3), $c('PADM 203', 'Local Government', 3), $entr202, $minor()],
                ],
                3 => [
                    1 => [$c('PADM 301', 'Government Budgeting Process', 3), $c('PADM 309', 'Public Organization Structure and Behavior', 3),
                          $c('PADM 307', 'Administration Theory and Behavior', 3),
                          $c('MANG 305', 'Application to Computer Technology', 3, 'Source titles MANG 305 "Applications to Computer Science" here.'),
                          $c('ECON 313', 'Business Statistics I', 3, 'Source titles ECON 313 "Statistics for Economics I" here.'), $minor(), $minor()],
                    2 => [$c('PADM 306', 'Administrative Law', 3), $c('MGMT 306', 'Computer Science II', 3),
                          $c('ECON 314', 'Business Statistics II', 3, 'Source titles ECON 314 "Statistics for Economics II" here.'),
                          $c('PADM 315', 'Public Policy Making and Analysis', 3), $minor(), $c('PADM 308', 'Research Methods', 3)],
                ],
                4 => [
                    1 => [$c('PADM 407', 'Government Planning Process', 3), $c('PADM 403', 'Public Personnel Administration', 3),
                          $c('PADM 405', 'Public Finance Administration', 3), $c('PADM 401', 'Comparative Public Administration', 3),
                          $tbc('Source lists PADM 407 Government Planning Process twice in this semester.'), $minor()],
                    2 => [$c('PADM 402', 'Rural Development Administration', 3), $c('PADM 408', 'Thesis', 6),
                          $c('LEAD 418', 'Leadership and Ethics', 3), $minor()],
                ],
            ],
            'minor' => [
                'label' => 'Minor in Public Administration (for non-Public Administration majors)',
                'courses' => [
                    $c('PADM 201', 'Introduction to Public Administration I', 3),
                    $c('PADM 202', 'Introduction to Public Administration II', 3),
                    $c('PADM 203', 'Local Government', 3, 'Source lists the minor as "PADM 204 Local Governments".'),
                    $c('PADM 306', 'Administrative Law', 3),
                    $either([
                        $c('PADM 307', 'Administration Theory and Behavior', 3, 'Source lists the minor as "Administrative Theories and Practice".'),
                        $c('PADM 309', 'Public Organization Structure and Behavior', 3),
                    ]),
                    $either([
                        $c('PADM 301', 'Government Budgeting Process', 3, 'Source lists the minor as "PADM 311 Government Budgetary Processes".'),
                        $c('PADM 315', 'Public Policy Making and Analysis', 3),
                    ]),
                ],
            ],
        ],

        /* ======================== College of Education ======================== */
        'BEDPE' => [
            'department' => 'PRED', 'name' => 'Bachelor of Education in Primary Education', 'award' => 'BEd. Primary Education',
            'level' => 'bachelor', 'years' => 4, 'credits' => null, 'plan' => [],
        ],

        'BEDSE' => [
            'department' => 'SCED', 'name' => 'Bachelor of Education in Secondary Education', 'award' => 'BEd. Secondary Education',
            'level' => 'bachelor', 'years' => 4, 'credits' => 147,
            'description' => 'Students take one content-area concentration (English Education or Mathematics Education) in the content-area slots. The source states 145/147 credits for graduation.',
            'plan' => [
                1 => [
                    1 => [$engl101, $fren101, $either([$math101, $c('MATH 107', 'Pre-Calculus', 4)]), $sosc101, $phsc101, $cosc101, $phed101],
                    2 => [$engl102, $fren102, $either([$math102, $c('MATH 108', 'Mathematics for Decision Making', 4)]), $sosc102, $phsc102, $cosc102, $phed102],
                ],
                2 => [
                    1 => [$engl201, $c('EDUC 201', 'Introduction to Education', 3), $c('EDUC 203', 'Human Development', 3),
                          $c('EDUC 205', 'Educational Philosophy', 3), $minor(), $content(), $entr201],
                    2 => [$engl202, $c('EDUC 202', 'Educational Psychology', 3), $c('EDUC 204', 'Curriculum Principles', 3),
                          $content(), $minor(), $minor(), $entr202, $phil102],
                ],
                3 => [
                    1 => [$c('EDUC 301', 'Testing and Evaluation', 3), $c('EDUC 303', 'Teaching Methods & Instructional Media', 3),
                          $c('EDUC 305', 'Sociology of Education', 3), $content(), $content(), $minor()],
                    2 => [$c('EDUC 302', 'Classroom Management', 3), $c('EDUC 304', 'School Administration', 3),
                          $c('EDUC 306', 'Education Statistics', 3), $content(), $content(), $minor()],
                ],
                4 => [
                    1 => [$c('EDUC 401', 'Guidance and Counseling', 3), $c('EDUC 403', 'Research Methods (for Education Students)', 3),
                          $c('EDUC 405', 'Educational Planning', 3), $content(), $minor(), $c('LEAD 417', 'Leadership and Ethics', 3)],
                    2 => [$c('EDUC 402', 'Comparative Education', 3), $c('EDUC 404', 'Teaching Practice', 6), $c('EDUC 408', 'Thesis Writing', 6)],
                ],
            ],
            'concentrations' => [
                'English Education' => [
                    $c('ENGL 203', 'Fundamentals of Literature', 3),
                    $c('ENGL 204', 'Survey of African Literature', 3),
                    $c('ENGL 301', 'Linguistics I', 3),
                    $c('ENGL 303', 'African Poetry', 3),
                    $c('ENGL 302', 'Linguistics II', 3),
                    $c('ENGL 304', 'Liberian Literature', 3),
                    $c('ENGL 306', 'Teaching English Language in Secondary School', 3),
                ],
                'Mathematics Education' => [
                    $c('MATH 201', 'Calculus I', 3),
                    $c('MATH 204', 'Geometry for Teachers', 3),
                    $c('MATH 206', 'Statistics & Probability', 3),
                    $c('MATH 301', 'Complex Numbers & Numerical Analysis', 3),
                    $c('MATH 303', 'Logic & Reasoning', 3),
                    $c('MATH 302', 'College Algebra', 3),
                    $c('MATH 304', 'Method of Teaching Math in Secondary School', 3),
                ],
            ],
            'minor' => [
                'label' => 'Minor in Education (for non-Education majors)',
                'courses' => [
                    $c('EDUC 201', 'Introduction to Education', 3),
                    $c('EDUC 202', 'Educational Psychology', 3),
                    $c('EDUC 205', 'Educational Philosophy', 3),
                    $c('EDUC 302', 'Classroom Management', 3),
                    $c('EDUC 303', 'Teaching Methods & Instructional Media', 3),
                    $either([$c('EDUC 301', 'Testing and Evaluation', 3), $c('EDUC 402', 'Comparative Education', 3)]),
                ],
            ],
        ],

        'BEDGC' => [
            'department' => 'GDCN', 'name' => 'Bachelor of Education in Guidance & Counseling', 'award' => 'BEd. Guidance & Counseling',
            'level' => 'bachelor', 'years' => 4, 'credits' => null, 'plan' => [],
        ],
        'BEDADM' => [
            'department' => 'EDAD', 'name' => 'Bachelor of Education in Educational Administration', 'award' => 'BEd. Administration',
            'level' => 'bachelor', 'years' => 4, 'credits' => null, 'plan' => [],
        ],
        'BSECE' => [
            'department' => 'ECED', 'name' => 'Bachelor of Science in Early Childhood Education', 'award' => 'BSc. Early Childhood',
            'level' => 'bachelor', 'years' => 4, 'credits' => null, 'plan' => [],
        ],
        'AAEDU' => [
            'department' => 'PRED', 'name' => 'Associate of Arts in Education', 'award' => 'AA Degree in Education',
            'level' => 'associate', 'years' => 2, 'credits' => null, 'plan' => [],
            'description' => 'The source names no department for this programme; placed under Primary Education pending confirmation.',
        ],
        'CEDU' => [
            'department' => 'PRED', 'name' => 'C-Certificate in Education', 'award' => 'C – Certificate in Education',
            'level' => 'certificate', 'years' => 1, 'credits' => null, 'plan' => [],
            'description' => 'The source names no department or duration for this programme; placed under Primary Education pending confirmation.',
        ],

        /* =================== College of Information Technology =================== */
        'BSIT' => [
            'department' => 'IT', 'name' => 'Bachelor of Science in Information Technology', 'award' => 'BSc. Information Technology',
            'level' => 'bachelor', 'years' => 4, 'credits' => null,
            'plan' => [
                1 => [
                    1 => [$engl101, $c('MATH 107', 'Pre-Calculus', 4), $c('FREN 101', 'Fundamentals of French I', 3, 'Source titles FREN 101 "Introduction to French I" here.'),
                          $c('IT 100', 'Mentoring and Professional Development', 1), $sosc101,
                          $c('IT 101', 'Introduction to Information Technology', 3), $c('IT 103', 'Computer Application I', 3)],
                    2 => [$engl102, $c('MATH 108', 'Mathematics for Decision Making', 3, 'Source lists 3 credits here (4 in other programmes).'), $sosc102,
                          $c('IT 102', 'Computer Desktop Management', 3), $c('IT 104', 'Fundamentals of Networking', 3),
                          $c('IT 106', 'Introduction to Database Management System', 3), $c('IT 108', 'Programming Fundamentals', 3)],
                ],
                2 => [
                    1 => [$c('IT 201', 'Computer Hardware and Software Maintenance', 3), $c('IT 203', 'Network Administration (CCNA, R&S)', 3),
                          $c('IT 205', 'Data Communication', 3), $c('IT 207', 'Introduction to Web Development', 3), $engl201,
                          $c('IT 213', 'Database Management System (MySQL)', 3),
                          $c('IT 212', 'Organizational Behavior', 3, 'Code confirmed by the 2026 billing forms ("IT 212 Organization Behavior").')],
                    2 => [$c('IT 202', 'Cyber Security Concepts', 3), $c('IT 206', 'Management Information System', 3),
                          $c('IT 208', 'Introduction to Software Engineering', 3), $c('IT 210', 'Network Security', 3),
                          $c('IT 212B', 'Introduction to Machine Learning & AI', 3, 'Provisional code: the academic PDF also prints IT 212 for this course, and the billing forms use IT 212 for Organizational Behavior.'),
                          $c('IT 214', 'Fundamentals of Data Analysis', 3),
                          $c('ENT 216', 'Leadership & Entrepreneurship I', 1)],
                ],
                3 => [
                    1 => [$c('IT 301', 'System Structure Analysis and Design', 3), $c('IT 308', 'Multimedia', 3),
                          $c('IT 300', 'Introduction to Data Science', 3), $c('IT 305', 'Programming (Java)', 3),
                          $c('IT 307', 'Unix Administration / Linux System', 3), $c('IT 309', 'Software Development and Testing', 3),
                          $c('IT 311', 'E-commerce & Digital Marketing', 3)],
                    2 => [$c('IT 302', 'Cloud Computing Technologies', 3), $c('IT 304', 'Data Analysis & Visualization (Python & R)', 3),
                          $c('IT 306', 'IT Risk Management', 3), $c('IT 314', 'Research Methodology in Computer Science', 3),
                          $c('IT 310', 'Network Management', 3), $c('IT 312', 'IT Project Management', 3),
                          $c('IT 313', 'Wireless & Mobile Networking', 3)],
                ],
                4 => [
                    1 => [$c('IT 401', 'IT Governance and Leadership', 3), $c('IT 403', 'Network Information Security', 3),
                          $c('IT 405', 'IT Law', 3), $c('IT 407', 'Advanced System Administration', 3),
                          $c('IT 409', 'Advanced Web Development (PHP, ASP.NET)', 3), $c('IT 410', 'Big Data Technologies', 3)],
                    2 => [$c('IT 402', 'Software Engineering Environment', 3), $c('IT 404', 'Mobile Computing', 3),
                          $c('IT 406', 'Virtualization (VMware, Citrix, Hyper-V)', 3), $c('IT 408', 'Project (Interim Thesis)', 6)],
                ],
            ],
        ],
    ],

    /* ------------------------------------------------------------------ */
    /*  Courses outside the programme plans                                */
    /* ------------------------------------------------------------------ */
    'additional_courses' => [
        $c('ENG 033', 'English Skills', 3, 'Developmental English. On the 2026 billing forms; not in the academic PDF.'),
    ],

    /*
     * Codes the 2026 billing forms use for catalogue courses. The PDF codes stay
     * official; these resolve to the same course. [billing code, catalogue code,
     * title on the form, credits on the form, note]
     */
    'course_aliases' => [
        ['NURS 101', 'NSG 101', 'Fundamentals of Nursing', 3, 'Billing form bills 3 credits; the academic PDF lists NSG 101 at 4.'],
        ['CISC 201', 'IT 201', 'Computer Troubleshooting & Software Maintenance', 3, 'PDF title: Computer Hardware and Software Maintenance.'],
        ['CISC 205', 'IT 205', 'Data Communication', 3, null],
        ['CISC 207', 'IT 207', 'Introduction to Web Development', 3, null],
        ['CISC 213', 'IT 213', 'Database Management System (MySQL)', 3, null],
    ],
    // Same code, different wording on the billing forms (recorded for reference).
    'billing_titles' => [
        'COSC 101' => 'General Computer Literacy',
        'COSC 102' => 'General Computer Literacy 2',
        'IT 212'   => 'Organization Behavior',
    ],

    /* ------------------------------------------------------------------ */
    /*  Tuition: charged per credit hour of every registered course        */
    /*  Rates from the 2026 billing forms: ENGL/MATH/SOSC/COSC/PHED/ACCT   */
    /*  at $10, BIOL/CHEM/NURS at $18, IT courses at $25.                  */
    /* ------------------------------------------------------------------ */
    'tuition_rates' => [
        'GENERAL' => ['General courses', 10.00, 'General education, business, education and liberal arts courses.'],
        'SCIENCE' => ['Health & laboratory science courses', 18.00, 'Biology, chemistry, physics and College of Health Science courses.'],
        'IT'      => ['Information technology courses', 25.00, 'College of Information Technology courses.'],
    ],
    // Band per course: by subject prefix first, then by the owning department; otherwise GENERAL.
    'tuition_rate_prefixes'    => ['BIOL' => 'SCIENCE', 'CHEM' => 'SCIENCE', 'PHYS' => 'SCIENCE'],
    'tuition_rate_departments' => ['NURS' => 'SCIENCE', 'PUBH' => 'SCIENCE', 'ENVS' => 'SCIENCE', 'MLT' => 'SCIENCE', 'IT' => 'IT'],

    /* ------------------------------------------------------------------ */
    /*  Registration fees (Business & Finance Office schedule)             */
    /* ------------------------------------------------------------------ */
    // code => [name, category, charged every semester]
    'fee_types' => [
        'TUI'  => ['Tuition Fee', 'tuition', 1],
        'REG'  => ['Registration', 'registration', 1],
        'LIB'  => ['Library Fee (Both)', 'library', 1],
        'MNT'  => ['Maintenance Fee', 'other', 1],
        'UTL'  => ['Utilities', 'other', 1],
        'ACT'  => ['Activities Fee', 'activity', 1],
        'BRK'  => ['Breakage Fee', 'other', 1],
        'MED'  => ['Medical (First Aid)', 'medical', 1],
        'ICT'  => ['ICT Fees', 'other', 1],
        'IDC'  => ['ID Card', 'other', 0],
        'HBK'  => ['Handbook', 'other', 0],
        'RTS'  => ['ROTC T-Shirt', 'other', 0],
        'MAT'  => ['Matriculation Fee', 'registration', 0],
        'SMS'  => ['Student Management System', 'other', 1],
        'SCF'  => ['Student Council Fee', 'activity', 1],
    ],
    'fee_schedules' => [
        'academic_year' => '2026',
        // 1st installment = every registration fee + half the tuition; 2nd = the other half.
        'installments'  => [['1st Installment', 50, 0], ['2nd Installment', 50, 56]],
        'structures'    => [
            'REG-NEW' => ['Registration Fees - New Students', 'new', [
                'REG' => 25, 'LIB' => 10, 'MNT' => 10, 'UTL' => 5, 'ACT' => 20, 'BRK' => 13, 'MED' => 10, 'ICT' => 35,
                'IDC' => 10, 'HBK' => 2, 'RTS' => 10, 'MAT' => 15, 'SMS' => 10, 'SCF' => 5,
            ], 180],
            'REG-S1' => ['Registration Fees - Every 1st Semester', 'first_term', [
                'REG' => 25, 'LIB' => 10, 'MNT' => 10, 'UTL' => 5, 'ACT' => 20, 'BRK' => 13, 'MED' => 10, 'ICT' => 35,
                'IDC' => 10, 'SMS' => 10, 'SCF' => 5,
            ], 153],
            'REG-S2' => ['Registration Fees - Every 2nd Semester', 'second_term', [
                'REG' => 25, 'LIB' => 10, 'MNT' => 10, 'UTL' => 5, 'ACT' => 20, 'BRK' => 13, 'MED' => 10, 'ICT' => 35,
                'SMS' => 10, 'SCF' => 5,
            ], 143],
        ],
    ],

    /* ------------------------------------------------------------------ */
    /*  Grading scale (section 3 of the source)                            */
    /* ------------------------------------------------------------------ */
    'grading' => [
        // grade, min, max, grade point, remarks, counts as a pass
        ['A', 90.00, 100.00, 4.00, 'Excellent', 1],
        ['B', 80.00,  89.99, 3.00, 'Very Good', 1],
        ['C', 70.00,  79.99, 2.00, 'Good', 1],
        ['D', 60.00,  69.99, 1.00, 'Pass', 1],
        ['F',  0.00,  59.99, 0.00, 'Fail', 0],
    ],
    'pass_mark' => 60,

    /* ------------------------------------------------------------------ */
    /*  Academic years and semesters (sections 6 and 7)                    */
    /*  The source gives only the year names; the dates below are          */
    /*  placeholders for the registrar to adjust.                          */
    /* ------------------------------------------------------------------ */
    'academic_years' => ['2021', '2022', '2023', '2024', '2025', '2026'],
    'semesters' => [
        // year, name, number, start, end, registration start, registration end, exam start, exam end
        ['2026', 'Semester One', 1, '2026-02-02', '2026-06-19', '2026-01-12', '2026-02-13', '2026-06-08', '2026-06-19'],
        ['2026', 'Semester Two', 2, '2026-08-03', '2026-12-11', '2026-07-13', '2026-08-21', '2026-11-30', '2026-12-11'],
    ],
];
