<?php
/**
 * The university's teaching rooms, transcribed from "The list of the class rooms and numbers.pdf".
 *
 * Imported automatically on the next page load after this file changes
 * (Admin > Data Imports shows the result). By hand:  php database/data_import.php database/data/05_classrooms_bbiu.php
 *
 * A room is matched on its code, so correcting a capacity here and saving the
 * file updates the room rather than creating a second one.
 *
 * The list gives names and numbers only. Capacities and room types below are
 * the sensible reading of each name - a studio as a laboratory, a lecture room
 * as a lecture hall - and the Facilities Officer should confirm the seating
 * before the timetable relies on it (the timetable rejects a room that is too
 * small for a class).
 *
 * Each room: code / name / building / floor / capacity / type / projector / note
 */
declare(strict_types=1);

$r = static fn (string $code, string $name, string $building, int $floor, int $capacity,
                string $type, bool $projector, ?string $note = null): array =>
    compact('code', 'name', 'building', 'floor', 'capacity', 'type', 'projector', 'note');

return [
    'type'   => 'rooms',
    'source' => 'The list of the class rooms and numbers.pdf',

    // The blocks the rooms sit in. The list groups them by department.
    'buildings' => [
        'LR'  => ['name' => 'Lecture Rooms Block', 'floors' => 1],
        'ICT' => ['name' => 'ICT Department',      'floors' => 1],
        'ND'  => ['name' => 'Nursing Department',  'floors' => 1],
    ],

    'rooms' => [
        // ---- Lecture rooms. The list runs 102-105 and 107-109; there is no 106. ----
        $r('LR 102', 'Lecture Room 102', 'LR', 1, 40, 'lecture_hall', false, 'Seating not given in the list; confirm it.'),
        $r('LR 103', 'Lecture Room 103', 'LR', 1, 40, 'lecture_hall', false),
        $r('LR 104', 'Lecture Room 104', 'LR', 1, 40, 'lecture_hall', false),
        $r('LR 105', 'Lecture Room 105', 'LR', 1, 40, 'lecture_hall', false),
        $r('LR 107', 'Lecture Room 107', 'LR', 1, 40, 'lecture_hall', false, 'The list has no room 106.'),
        $r('LR 108', 'Lecture Room 108', 'LR', 1, 40, 'lecture_hall', false),
        $r('LR 109', 'Lecture Room 109', 'LR', 1, 40, 'lecture_hall', false),
        $r('LR Library 101', 'Library 101', 'LR', 1, 60, 'library', false, 'Printed "LR Library 101".'),

        // ---- ICT Department ----
        $r('ICT STUDIO 1', 'ICT Studio 1', 'ICT', 1, 30, 'laboratory', true),
        $r('ICT STUDIO 2', 'ICT Studio 2', 'ICT', 1, 30, 'laboratory', true),
        $r('ICT STUDIO 3', 'ICT Studio 3', 'ICT', 1, 30, 'laboratory', true),
        $r('IHSL', 'ICT Hardware Services Lab', 'ICT', 1, 25, 'laboratory', false, 'Printed "ICT Hardware Services Lab (IHSL)".'),

        // ---- Nursing Department. The list notes these have no official numbers. ----
        $r('ND STUDIO 1', 'Nursing Studio 1', 'ND', 1, 30, 'laboratory', false, 'The list notes the nursing classes carry no official room number.'),
        $r('ND STUDIO 2', 'Nursing Studio 2', 'ND', 1, 30, 'laboratory', false),
        $r('ND STUDIO 3', 'Nursing Studio 3', 'ND', 1, 30, 'laboratory', false),
        $r('ND STUDIO 4', 'Nursing Studio 4', 'ND', 1, 30, 'laboratory', false),
        $r('ND STUDIO 5', 'Nursing Studio 5', 'ND', 1, 30, 'laboratory', false),
    ],
];
