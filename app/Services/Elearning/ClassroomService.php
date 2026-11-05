<?php
declare(strict_types=1);

namespace App\Services\Elearning;

use App\Core\Database;
use App\Core\Setting;

/** Online class sessions: their timing, the join window and attendance. */
final class ClassroomService
{
    /** Students may join this many minutes before the start. */
    public const EARLY_JOIN_MINUTES = 15;

    public const PLATFORMS = [
        'jitsi'       => 'Jitsi Meet (link created automatically)',
        'zoom'        => 'Zoom',
        'google_meet' => 'Google Meet',
        'teams'       => 'Microsoft Teams',
        'other'       => 'Other platform',
    ];

    /** cancelled | upcoming | live | ended (or completed, once the lecturer marks it so). */
    public static function phase(array $session): string
    {
        if ($session['status'] === 'cancelled') {
            return 'cancelled';
        }
        $start = strtotime((string) $session['starts_at']);
        $end   = $start + (int) $session['duration_minutes'] * 60;
        $now   = time();
        if ($session['status'] === 'completed' || $now > $end) {
            return 'ended';
        }
        return $now >= $start ? 'live' : 'upcoming';
    }

    public static function canJoin(array $session): bool
    {
        $phase = self::phase($session);
        return $phase === 'live'
            || ($phase === 'upcoming' && strtotime((string) $session['starts_at']) - time() <= self::EARLY_JOIN_MINUTES * 60);
    }

    public static function endsAt(array $session): string
    {
        return date('Y-m-d H:i:s', strtotime((string) $session['starts_at']) + (int) $session['duration_minutes'] * 60);
    }

    /** A private Jitsi Meet room for the class: hard to guess, readable in the address bar. */
    public static function jitsiUrl(array $offering): string
    {
        $prefix = (string) preg_replace('/[^A-Za-z0-9]/', '', (string) Setting::get('institution_short_name', 'UNI'));
        $course = (string) preg_replace('/[^A-Za-z0-9]/', '', (string) $offering['code']);
        return 'https://meet.jit.si/' . $prefix . '-' . $course . '-' . $offering['section'] . '-' . bin2hex(random_bytes(5));
    }

    public static function recordJoin(int $sessionId, int $studentId): void
    {
        $now = date('Y-m-d H:i:s');
        Database::statement(
            'INSERT INTO class_session_attendance (session_id, student_id, first_joined_at, last_joined_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE last_joined_at = VALUES(last_joined_at), join_count = join_count + 1',
            [$sessionId, $studentId, $now, $now]
        );
    }

    /** Sessions for the given offerings with each one's attendance count, newest last. */
    public static function sessions(array $offeringIds, ?int $studentId = null): array
    {
        $extra  = $studentId !== null
            ? ', (SELECT a.first_joined_at FROM class_session_attendance a WHERE a.session_id = s.id AND a.student_id = ' . (int) $studentId . ') AS joined_at'
            : ', (SELECT COUNT(*) FROM class_session_attendance a WHERE a.session_id = s.id) AS attendees';
        return Database::select(
            'SELECT s.*, c.code, c.title AS course_title, o.section' . $extra . '
               FROM class_sessions s
               JOIN course_offerings o ON o.id = s.offering_id
               JOIN courses c          ON c.id = o.course_id
              WHERE s.offering_id IN (' . CourseAccess::inList($offeringIds) . ')
              ORDER BY s.starts_at'
        );
    }
}
