<?php
declare(strict_types=1);

namespace App\Services\Elearning;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Setting;
use App\Core\Upload;
use App\Services\Alerts\Alerts;
use RuntimeException;

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

    public const SESSION_RULES = [
        'title'            => 'required|max:150',
        'platform'         => 'required|in:jitsi,zoom,google_meet,teams,other',
        'starts_at'        => 'required',
        'duration_minutes' => 'required|integer',
        'meeting_url'      => 'nullable|max:500',
        'meeting_id'       => 'nullable|max:100',
        'passcode'         => 'nullable|max:100',
        'description'      => 'nullable',
    ];

    public const SESSION_LABELS = ['starts_at' => 'Date and time', 'duration_minutes' => 'Duration'];

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

    /** null for blank; the URL when it is a valid http(s) link; an exception otherwise. */
    public static function optionalUrl(string $url, string $label): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            throw new RuntimeException("{$label} must be a full web address starting with https://.");
        }
        return mb_substr($url, 0, 500);
    }

    /**
     * Validate and normalise class session input (dates, duration, meeting URL, Jitsi auto-room).
     * @throws RuntimeException on invalid dates or URLs
     */
    public static function normalizeSessionInput(array $data, array $offering, ?array $existing = null): array
    {
        $start = strtotime(str_replace('T', ' ', (string) ($data['starts_at'] ?? '')));
        if ($start === false) {
            throw new RuntimeException('Enter a valid date and time.');
        }
        $data['starts_at']        = date('Y-m-d H:i:s', $start);
        $data['duration_minutes'] = max(10, min(600, (int) ($data['duration_minutes'] ?? 60)));
        $data['description']      = trim((string) ($data['description'] ?? '')) ?: null;
        $data['meeting_id']       = trim((string) ($data['meeting_id'] ?? '')) ?: null;
        $data['passcode']         = trim((string) ($data['passcode'] ?? '')) ?: null;

        $url = self::optionalUrl((string) ($data['meeting_url'] ?? ''), 'Meeting link');
        if ($url === null) {
            if (($data['platform'] ?? '') !== 'jitsi') {
                throw new RuntimeException('Paste the meeting link from ' . (self::PLATFORMS[$data['platform']] ?? $data['platform']) . '.');
            }
            // Keep the room of an existing Jitsi class so links already shared still work.
            $url = $existing !== null && $existing['platform'] === 'jitsi' ? $existing['meeting_url'] : self::jitsiUrl($offering);
        }
        $data['meeting_url'] = $url;
        if (array_key_exists('recording_url', $data)) {
            $data['recording_url'] = self::optionalUrl((string) ($data['recording_url'] ?? ''), 'Recording link');
        }
        return $data;
    }

    public static function createSession(array $offering, array $data, int $userId, bool $notifyLecturers = false): int
    {
        $offeringId = (int) $offering['id'];
        Database::statement(
            'INSERT INTO class_sessions (offering_id, title, description, platform, meeting_url, meeting_id, passcode,
                                         starts_at, duration_minutes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$offeringId, $data['title'], $data['description'], $data['platform'], $data['meeting_url'], $data['meeting_id'],
             $data['passcode'], $data['starts_at'], $data['duration_minutes'], $userId]
        );
        $sessionId = Database::lastInsertId();
        Audit::log('create', 'elearning', 'class_sessions', (string) $sessionId, "Scheduled online class {$data['title']} for {$offering['code']}");
        self::notifyClass($offeringId, 'Online class scheduled: ' . $offering['code'],
            $data['title'] . ' on ' . fdatetime($data['starts_at']) . '.', '/portal/classes');

        if ($notifyLecturers) {
            $teachers = array_values(array_diff(Oversight::offeringTeachersUserIds($offeringId), [$userId]));
            Alerts::sendMany($teachers, 'class_scheduled', 'Online lesson scheduled: ' . $offering['code'],
                $data['title'] . ' on ' . fdatetime($data['starts_at']) . '.', '/teaching/' . $offeringId . '/classroom', 'info', 'calendar');
        }
        return $sessionId;
    }

    public static function updateSession(array $session, array $data): void
    {
        Database::statement(
            'UPDATE class_sessions SET title = ?, description = ?, platform = ?, meeting_url = ?, meeting_id = ?, passcode = ?,
                    starts_at = ?, duration_minutes = ?, recording_url = ?, status = IF(status = "cancelled", "scheduled", status)
              WHERE id = ?',
            [$data['title'], $data['description'], $data['platform'], $data['meeting_url'], $data['meeting_id'], $data['passcode'],
             $data['starts_at'], $data['duration_minutes'], $data['recording_url'] ?? $session['recording_url'], $session['id']]
        );
        Audit::log('update', 'elearning', 'class_sessions', (string) $session['id'], "Updated online class {$data['title']}");
    }

    public static function setSessionStatus(array $session, array $offering, string $action): void
    {
        $status = $action === 'cancel' ? 'cancelled' : 'completed';
        Database::statement('UPDATE class_sessions SET status = ? WHERE id = ?', [$status, $session['id']]);
        if ($action === 'cancel') {
            self::notifyClass((int) $session['offering_id'], 'Online class cancelled: ' . $offering['code'],
                $session['title'] . ' (' . fdatetime($session['starts_at']) . ') has been cancelled.', '/portal/classes', 'class_cancelled');
        }
        Audit::log('update', 'elearning', 'class_sessions', (string) $session['id'], "Marked online class {$session['title']} as {$status}");
    }

    public static function deleteSession(array $session): void
    {
        Database::statement('DELETE FROM class_sessions WHERE id = ?', [$session['id']]);
        Audit::log('delete', 'elearning', 'class_sessions', (string) $session['id'], 'Deleted online class ' . $session['title']);
    }

    /** Tell everyone on the class list (in-app notification, plus email/SMS if enabled). */
    /* ---------------- Course materials (notes, slides, readings) ---------------- */

    /** What a material upload accepts: documents, images and zipped sets, up to 25 MB. */
    public const MATERIAL_TYPES = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx',
                                   'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip'];
    public const MATERIAL_MAX_MB = 25;

    /**
     * Add or edit a course material. Shared by the lecturer's classroom and the
     * academic office, so a note shared from either place behaves the same.
     *
     * @param  array      $offering the class the material belongs to
     * @param  array      $input    title, description, week, kind, url, is_published
     * @param  array|null $upload   the $_FILES entry when kind is "file"
     * @return int the material's id
     * @throws RuntimeException when the title, link or file is unusable
     */
    public static function saveMaterial(array $offering, array $input, ?array $upload, int $userId, ?array $existing = null): int
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('Give the material a title.');
        }
        $week      = ($input['week'] ?? '') !== '' ? max(1, min(30, (int) $input['week'])) : null;
        $kind      = ($input['kind'] ?? 'file') === 'link' ? 'link' : 'file';
        $published = ($input['is_published'] ?? '1') === '1' ? 1 : 0;

        $file = ['path' => null, 'name' => null, 'size' => null];
        $link = null;
        if ($kind === 'link') {
            $link = self::optionalUrl((string) ($input['url'] ?? ''), 'Link');
            if ($link === null) {
                throw new RuntimeException('Enter the link (starting with https://).');
            }
        } elseif ($upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = Upload::storePrivate($upload, 'materials/' . (int) $offering['id'], self::MATERIAL_TYPES, self::MATERIAL_MAX_MB);
        } elseif ($existing === null || $existing['kind'] !== 'file') {
            throw new RuntimeException('Choose a file to upload.');
        } else {
            // Editing a file material without replacing the file: keep the one on disk.
            $file = ['path' => $existing['file_path'], 'name' => $existing['file_name'], 'size' => $existing['file_size']];
        }

        $description = trim((string) ($input['description'] ?? '')) ?: null;
        if ($existing !== null) {
            Database::statement(
                'UPDATE course_materials SET title = ?, description = ?, week = ?, kind = ?, file_path = ?, file_name = ?,
                        file_size = ?, url = ?, is_published = ? WHERE id = ?',
                [$title, $description, $week, $kind, $file['path'], $file['name'], $file['size'], $link, $published, $existing['id']]
            );
            // A replaced file leaves the old one behind.
            if ($existing['file_path'] !== null && $existing['file_path'] !== $file['path']) {
                Upload::delete($existing['file_path']);
            }
            return (int) $existing['id'];
        }

        Database::statement(
            'INSERT INTO course_materials (offering_id, title, description, week, kind, file_path, file_name, file_size, url, is_published, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $offering['id'], $title, $description, $week, $kind,
             $file['path'], $file['name'], $file['size'], $link, $published, $userId]
        );
        $materialId = Database::lastInsertId();

        if ($published === 1) {
            self::notifyClass((int) $offering['id'], 'New course material: ' . $offering['code'], $title,
                '/portal/courses/' . (int) $offering['id'], 'material_shared');
        }
        return $materialId;
    }

    public static function deleteMaterial(array $material): void
    {
        Database::statement('DELETE FROM course_materials WHERE id = ?', [$material['id']]);
        Upload::delete($material['file_path']);
    }

    public static function notifyClass(int $offeringId, string $title, string $message, string $link, string $event = 'class_scheduled'): void
    {
        $userIds = array_column(Database::select(
            'SELECT s.user_id FROM course_registrations cr JOIN students s ON s.id = cr.student_id
              WHERE cr.offering_id = ? AND cr.status <> "dropped"',
            [$offeringId]
        ), 'user_id');
        Alerts::sendMany($userIds, $event, $title, $message, $link, 'info', 'calendar');
    }
}
