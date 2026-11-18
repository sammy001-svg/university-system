<?php
declare(strict_types=1);

namespace App\Services\Academic;

use App\Core\Database;

/**
 * Timetable and examination clash detection.
 *
 * A venue can hold one class at a time, a lecturer can be in one place at a
 * time, and a student can attend one class at a time - so those clashes are
 * refused outright. Looser problems (a room that is too small, a room under
 * maintenance, two courses of the same cohort at the same hour) are reported
 * as warnings and the slot is still saved.
 *
 * Two periods overlap when each starts before the other ends; touching
 * periods (10:00-12:00 and 12:00-14:00) do not overlap.
 */
final class ScheduleConflict
{
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    /** MySQL DAYOFWEEK(): 1 = Sunday ... 7 = Saturday. */
    private const DAY_NUMBER = ['sunday' => 1, 'monday' => 2, 'tuesday' => 3, 'wednesday' => 4, 'thursday' => 5, 'friday' => 6, 'saturday' => 7];

    /**
     * Check a proposed timetable slot.
     *
     * @param array    $slot     offering_id, semester_id, day_of_week, start_time, end_time, room_id
     * @param int|null $ignoreId the slot being edited, so it does not clash with itself
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function forSlot(array $slot, ?int $ignoreId = null): array
    {
        $errors = $warnings = [];
        $day    = (string) $slot['day_of_week'];
        $start  = self::time($slot['start_time']);
        $end    = self::time($slot['end_time']);
        $roomId = (int) ($slot['room_id'] ?? 0) ?: null;
        $offeringId = (int) $slot['offering_id'];
        $semesterId = (int) $slot['semester_id'];
        $ignore     = $ignoreId ?? 0;

        if ($end <= $start) {
            return ['errors' => ['The class must end after it starts.'], 'warnings' => []];
        }
        if (!in_array($day, self::DAYS, true)) {
            return ['errors' => ['Choose a day of the week.'], 'warnings' => []];
        }
        $offering = Database::selectOne(
            'SELECT o.*, c.code, c.title FROM course_offerings o JOIN courses c ON c.id = o.course_id WHERE o.id = ?',
            [$offeringId]
        );
        if ($offering === null) {
            return ['errors' => ['That class is no longer in the system.'], 'warnings' => []];
        }
        $when   = ucfirst($day) . ' ' . self::show($start) . '-' . self::show($end);
        $others = [$semesterId, $day, $ignore, $end, $start];   // semester, day, not-itself, overlap pair

        /* ---- the same class twice at once ---- */
        foreach (Database::select(
            'SELECT start_time, end_time FROM timetable_slots
              WHERE offering_id = ? AND semester_id = ? AND day_of_week = ? AND id <> ? AND start_time < ? AND ? < end_time',
            [$offeringId, $semesterId, $day, $ignore, $end, $start]
        ) as $row) {
            $errors[] = self::label($offering) . ' already has a class on ' . $when
                . ' (' . self::show($row['start_time']) . '-' . self::show($row['end_time']) . ').';
        }

        /* ---- the venue ---- */
        if ($roomId !== null) {
            $room = Database::selectOne('SELECT * FROM rooms WHERE id = ?', [$roomId]);
            $name = $room === null ? 'That room' : $room['code'] . ' ' . $room['name'];

            foreach (Database::select(
                'SELECT ts.start_time, ts.end_time, ts.session_type, c.code, c.title, o.section
                   FROM timetable_slots ts
                   JOIN course_offerings o ON o.id = ts.offering_id
                   JOIN courses c          ON c.id = o.course_id
                  WHERE ts.room_id = ? AND ts.semester_id = ? AND ts.day_of_week = ? AND ts.id <> ?
                    AND ts.start_time < ? AND ? < ts.end_time',
                array_merge([$roomId], $others)
            ) as $row) {
                $errors[] = $name . ' is already taken on ' . $when . ' by ' . self::label($row)
                    . ' (' . self::show($row['start_time']) . '-' . self::show($row['end_time']) . ').';
            }

            foreach (Database::select(
                'SELECT e.exam_date, e.start_time, e.duration_mins, c.code, c.title, o.section
                   FROM exams e
                   JOIN course_offerings o ON o.id = e.offering_id
                   JOIN courses c          ON c.id = o.course_id
                  WHERE e.room_id = ? AND e.semester_id = ? AND e.status <> "cancelled" AND DAYOFWEEK(e.exam_date) = ?
                    AND e.start_time < ? AND ? < ADDTIME(e.start_time, SEC_TO_TIME(e.duration_mins * 60))',
                [$roomId, $semesterId, self::DAY_NUMBER[$day], $end, $start]
            ) as $row) {
                $errors[] = $name . ' is booked for the ' . self::label($row) . ' examination on '
                    . fdate($row['exam_date']) . ' at ' . self::show($row['start_time']) . '.';
            }

            if ($room !== null && $room['status'] !== 'available') {
                $warnings[] = $name . ' is marked "' . humanize($room['status']) . '".';
            }
            $expected = max((int) $offering['enrolled_count'], 0);
            if ($room !== null && $expected > (int) $room['capacity']) {
                $warnings[] = $name . ' seats ' . (int) $room['capacity'] . ' but ' . $expected . ' students are registered for this class.';
            }
        } elseif ($offering['delivery_mode'] === 'physical') {
            $warnings[] = 'No room has been set for this class yet.';
        }

        /* ---- the lecturer ---- */
        $staff = self::lecturers($offeringId);
        if ($staff !== []) {
            $in = implode(',', $staff);
            foreach (Database::select(
                'SELECT DISTINCT ts.start_time, ts.end_time, c.code, c.title, o.section,
                        CONCAT_WS(" ", u.title, u.first_name, u.last_name) AS lecturer_name
                   FROM timetable_slots ts
                   JOIN course_offerings o ON o.id = ts.offering_id
                   JOIN courses c          ON c.id = o.course_id
              LEFT JOIN staff st           ON st.id = o.lecturer_id
              LEFT JOIN users u            ON u.id = st.user_id
                  WHERE ts.semester_id = ? AND ts.day_of_week = ? AND ts.id <> ? AND ts.start_time < ? AND ? < ts.end_time
                    AND (o.lecturer_id IN (' . $in . ')
                         OR EXISTS (SELECT 1 FROM offering_lecturers ol WHERE ol.offering_id = o.id AND ol.staff_id IN (' . $in . ')))',
                $others
            ) as $row) {
                $errors[] = trim((string) $row['lecturer_name']) !== ''
                    ? $row['lecturer_name'] . ' already teaches ' . self::label($row) . ' on ' . $when . '.'
                    : 'The lecturer of this class already teaches ' . self::label($row) . ' on ' . $when . '.';
            }
        }

        /* ---- the students ---- */
        foreach (Database::select(
            'SELECT c.code, c.title, o.section, COUNT(DISTINCT mine.student_id) AS students
               FROM timetable_slots ts
               JOIN course_offerings o ON o.id = ts.offering_id
               JOIN courses c          ON c.id = o.course_id
               JOIN course_registrations theirs ON theirs.offering_id = o.id AND theirs.status <> "dropped"
               JOIN course_registrations mine   ON mine.student_id = theirs.student_id AND mine.offering_id = ? AND mine.status <> "dropped"
              WHERE ts.semester_id = ? AND ts.day_of_week = ? AND ts.id <> ? AND ts.start_time < ? AND ? < ts.end_time
              GROUP BY o.id, c.code, c.title, o.section',
            array_merge([$offeringId], $others)
        ) as $row) {
            $errors[] = (int) $row['students'] . ' student(s) registered for this class are already in '
                . self::label($row) . ' on ' . $when . '.';
        }

        /* ---- the cohort's plan of study (students who will be expected to take both) ---- */
        foreach (Database::select(
            'SELECT DISTINCT c.code, c.title, o.section, p.name AS program_name, theirs.year_of_study, theirs.semester_number
               FROM timetable_slots ts
               JOIN course_offerings o  ON o.id = ts.offering_id
               JOIN courses c           ON c.id = o.course_id
               JOIN program_courses theirs ON theirs.course_id = o.course_id
               JOIN program_courses mine   ON mine.program_id = theirs.program_id AND mine.year_of_study = theirs.year_of_study
                                          AND mine.semester_number = theirs.semester_number AND mine.course_id = ?
               JOIN programs p          ON p.id = theirs.program_id
              WHERE ts.semester_id = ? AND ts.day_of_week = ? AND ts.id <> ? AND ts.start_time < ? AND ? < ts.end_time
                AND theirs.course_id <> mine.course_id
                AND NOT (theirs.choice_group IS NOT NULL AND theirs.choice_group = mine.choice_group)
                AND theirs.course_type IN ("core", "common") AND mine.course_type IN ("core", "common")',
            array_merge([(int) $offering['course_id']], $others)
        ) as $row) {
            $warnings[] = $row['program_name'] . ' year ' . (int) $row['year_of_study'] . ' semester ' . (int) $row['semester_number']
                . ' students take both this course and ' . self::label($row) . ', which is on at the same time.';
        }

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * Check a proposed examination.
     *
     * @param array $exam offering_id, semester_id, exam_date, start_time, duration_mins, room_id, invigilator_id
     * @return array{errors: list<string>, warnings: list<string>}
     */
    public static function forExam(array $exam, ?int $ignoreId = null): array
    {
        $errors = $warnings = [];
        $start  = self::time($exam['start_time']);
        $end    = date('H:i:s', strtotime($start) + max(1, (int) $exam['duration_mins']) * 60);
        $date   = (string) $exam['exam_date'];
        $roomId = (int) ($exam['room_id'] ?? 0) ?: null;
        $offeringId = (int) $exam['offering_id'];
        $semesterId = (int) $exam['semester_id'];
        $ignore     = $ignoreId ?? 0;
        $day        = strtolower(date('l', strtotime($date)));
        $when       = fdate($date) . ' ' . self::show($start) . '-' . self::show($end);

        $offering = Database::selectOne(
            'SELECT o.*, c.code, c.title FROM course_offerings o JOIN courses c ON c.id = o.course_id WHERE o.id = ?',
            [$offeringId]
        );
        if ($offering === null) {
            return ['errors' => ['That class is no longer in the system.'], 'warnings' => []];
        }

        if ($roomId !== null) {
            $room = Database::selectOne('SELECT * FROM rooms WHERE id = ?', [$roomId]);
            $name = $room === null ? 'That room' : $room['code'] . ' ' . $room['name'];

            foreach (Database::select(
                'SELECT e.start_time, e.duration_mins, c.code, c.title, o.section
                   FROM exams e
                   JOIN course_offerings o ON o.id = e.offering_id
                   JOIN courses c          ON c.id = o.course_id
                  WHERE e.room_id = ? AND e.exam_date = ? AND e.status <> "cancelled" AND e.id <> ?
                    AND e.start_time < ? AND ? < ADDTIME(e.start_time, SEC_TO_TIME(e.duration_mins * 60))',
                [$roomId, $date, $ignore, $end, $start]
            ) as $row) {
                $errors[] = $name . ' is already taken on ' . $when . ' by the ' . self::label($row) . ' examination ('
                    . self::show($row['start_time']) . ', ' . (int) $row['duration_mins'] . ' minutes).';
            }

            foreach (Database::select(
                'SELECT ts.start_time, ts.end_time, c.code, c.title, o.section
                   FROM timetable_slots ts
                   JOIN course_offerings o ON o.id = ts.offering_id
                   JOIN courses c          ON c.id = o.course_id
                  WHERE ts.room_id = ? AND ts.semester_id = ? AND ts.day_of_week = ? AND ts.start_time < ? AND ? < ts.end_time',
                [$roomId, $semesterId, $day, $end, $start]
            ) as $row) {
                $warnings[] = $name . ' has a weekly class in it on ' . ucfirst($day) . 's: ' . self::label($row)
                    . ' ' . self::show($row['start_time']) . '-' . self::show($row['end_time']) . '. Free the room or move the class.';
            }

            if ($room !== null && $room['status'] !== 'available') {
                $warnings[] = $name . ' is marked "' . humanize($room['status']) . '".';
            }
            $candidates = (int) Database::scalar(
                'SELECT COUNT(*) FROM course_registrations WHERE offering_id = ? AND status <> "dropped"', [$offeringId]
            );
            if ($room !== null && $candidates > (int) $room['capacity']) {
                $warnings[] = $name . ' seats ' . (int) $room['capacity'] . ' but ' . $candidates . ' candidates are expected.';
            }
        } else {
            $warnings[] = 'No examination room has been set.';
        }

        /* ---- candidates sitting two papers at once ---- */
        foreach (Database::select(
            'SELECT c.code, c.title, o.section, e.start_time, COUNT(DISTINCT mine.student_id) AS students
               FROM exams e
               JOIN course_offerings o ON o.id = e.offering_id
               JOIN courses c          ON c.id = o.course_id
               JOIN course_registrations theirs ON theirs.offering_id = o.id AND theirs.status <> "dropped"
               JOIN course_registrations mine   ON mine.student_id = theirs.student_id AND mine.offering_id = ? AND mine.status <> "dropped"
              WHERE e.exam_date = ? AND e.status <> "cancelled" AND e.id <> ?
                AND e.start_time < ? AND ? < ADDTIME(e.start_time, SEC_TO_TIME(e.duration_mins * 60))
              GROUP BY e.id, c.code, c.title, o.section, e.start_time',
            [$offeringId, $date, $ignore, $end, $start]
        ) as $row) {
            $errors[] = (int) $row['students'] . ' candidate(s) also sit ' . self::label($row) . ' at '
                . self::show($row['start_time']) . ' that day.';
        }

        /* ---- the invigilator ---- */
        if (!empty($exam['invigilator_id'])) {
            foreach (Database::select(
                'SELECT c.code, c.title, o.section, e.start_time FROM exams e
                   JOIN course_offerings o ON o.id = e.offering_id
                   JOIN courses c          ON c.id = o.course_id
                  WHERE e.invigilator_id = ? AND e.exam_date = ? AND e.status <> "cancelled" AND e.id <> ?
                    AND e.start_time < ? AND ? < ADDTIME(e.start_time, SEC_TO_TIME(e.duration_mins * 60))',
                [(int) $exam['invigilator_id'], $date, $ignore, $end, $start]
            ) as $row) {
                $errors[] = 'That invigilator is already assigned to ' . self::label($row) . ' at ' . self::show($row['start_time']) . ' that day.';
            }
        }

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * Every clash already in a semester's timetable, for the conflicts report.
     * @return list<array{type:string, detail:string, a:array, b:array}>
     */
    public static function auditSemester(int $semesterId): array
    {
        $slots = Database::select(
            'SELECT ts.*, c.code, c.title, o.section, o.lecturer_id, o.enrolled_count, r.code AS room_code, r.name AS room_name, r.capacity,
                    CONCAT_WS(" ", u.title, u.first_name, u.last_name) AS lecturer_name
               FROM timetable_slots ts
               JOIN course_offerings o ON o.id = ts.offering_id
               JOIN courses c          ON c.id = o.course_id
          LEFT JOIN rooms r            ON r.id = ts.room_id
          LEFT JOIN staff st           ON st.id = o.lecturer_id
          LEFT JOIN users u            ON u.id = st.user_id
              WHERE ts.semester_id = ?',
            [$semesterId]
        );
        $byId = array_column($slots, null, 'id');

        // Students sitting in two classes at once, counted once per overlapping pair.
        $shared = [];
        foreach (Database::select(
            'SELECT a.id AS a_id, b.id AS b_id, COUNT(DISTINCT ra.student_id) AS students
               FROM timetable_slots a
               JOIN timetable_slots b ON b.semester_id = a.semester_id AND b.day_of_week = a.day_of_week AND b.id > a.id
                                     AND a.start_time < b.end_time AND b.start_time < a.end_time
               JOIN course_registrations ra ON ra.offering_id = a.offering_id AND ra.status <> "dropped"
               JOIN course_registrations rb ON rb.offering_id = b.offering_id AND rb.student_id = ra.student_id AND rb.status <> "dropped"
              WHERE a.semester_id = ?
              GROUP BY a.id, b.id',
            [$semesterId]
        ) as $row) {
            $shared[$row['a_id'] . '-' . $row['b_id']] = (int) $row['students'];
        }

        $out = [];
        foreach ($slots as $a) {
            foreach ($slots as $b) {
                if ((int) $b['id'] <= (int) $a['id'] || $a['day_of_week'] !== $b['day_of_week']
                    || $a['start_time'] >= $b['end_time'] || $b['start_time'] >= $a['end_time']) {
                    continue;
                }
                $when = ucfirst($a['day_of_week']) . ' ' . self::show($a['start_time']) . '-' . self::show($a['end_time']);
                if ($a['room_id'] !== null && $a['room_id'] === $b['room_id']) {
                    $out[] = self::clash('Room double-booked', $a['room_code'] . ' ' . $a['room_name'] . ' holds both classes on ' . $when . '.', $a, $b);
                }
                if ($a['lecturer_id'] !== null && $a['lecturer_id'] === $b['lecturer_id']) {
                    $out[] = self::clash('Lecturer double-booked', ($a['lecturer_name'] ?: 'The lecturer') . ' is timetabled for both on ' . $when . '.', $a, $b);
                }
                if ((int) $a['offering_id'] === (int) $b['offering_id']) {
                    $out[] = self::clash('Class scheduled twice', self::label($a) . ' has two slots on ' . $when . '.', $a, $b);
                }
                $n = $shared[$a['id'] . '-' . $b['id']] ?? 0;
                if ($n > 0) {
                    $out[] = self::clash('Students double-booked', $n . ' student(s) are registered for both classes on ' . $when . '.', $a, $b);
                }
            }
        }
        // Rooms that are too small, or unavailable.
        foreach ($slots as $s) {
            if ($s['room_id'] !== null && (int) $s['enrolled_count'] > (int) $s['capacity']) {
                $out[] = self::clash('Room too small', $s['room_code'] . ' seats ' . (int) $s['capacity'] . ' but '
                    . (int) $s['enrolled_count'] . ' students are registered.', $s, null);
            }
        }
        return $out;
    }

    /** Staff ids teaching an offering (the lead lecturer and any co-lecturers). */
    private static function lecturers(int $offeringId): array
    {
        return array_map('intval', array_column(Database::select(
            'SELECT lecturer_id AS id FROM course_offerings WHERE id = ? AND lecturer_id IS NOT NULL
             UNION SELECT staff_id FROM offering_lecturers WHERE offering_id = ?',
            [$offeringId, $offeringId]
        ), 'id'));
    }

    private static function clash(string $type, string $detail, array $a, ?array $b): array
    {
        return ['type' => $type, 'detail' => $detail, 'a' => $a, 'b' => $b];
    }

    private static function label(array $row): string
    {
        return trim($row['code'] . ' ' . ($row['title'] ?? '') . (isset($row['section']) ? ' (section ' . $row['section'] . ')' : ''));
    }

    private static function time(mixed $value): string
    {
        $t = strtotime((string) $value) ?: strtotime('00:00');
        return date('H:i:s', $t);
    }

    private static function show(string $time): string
    {
        return date('H:i', strtotime($time));
    }
}
