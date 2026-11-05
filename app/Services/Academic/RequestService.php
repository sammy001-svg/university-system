<?php
declare(strict_types=1);

namespace App\Services\Academic;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Notify;
use App\Services\Alerts\Alerts;
use RuntimeException;

/**
 * Formal student requests (petitions). A decision is recorded with a note; an approved
 * leave of absence, deferment, withdrawal, readmission or change of programme is applied
 * to the student record straight away. Other requests (grade appeal, credit transfer,
 * late registration, name change) are actioned by the office concerned.
 */
final class RequestService
{
    public const TYPES = [
        'leave_of_absence'  => 'Leave of absence',
        'deferment'         => 'Deferment of studies',
        'withdrawal'        => 'Withdrawal from the university',
        'programme_change'  => 'Change of programme',
        'late_registration' => 'Late registration / add a course',
        'grade_appeal'      => 'Grade appeal',
        'credit_transfer'   => 'Credit transfer / exemption',
        'name_change'       => 'Change of name on records',
        'readmission'       => 'Readmission',
        'other'             => 'Other request',
    ];
    /** What an approval does to the student record. */
    public const EFFECTS = [
        'leave_of_absence' => 'Student status set to "deferred".',
        'deferment'        => 'Student status set to "deferred".',
        'withdrawal'       => 'Student status set to "withdrawn".',
        'readmission'      => 'Student status set back to "active".',
        'programme_change' => 'Student moved to the requested programme.',
    ];

    public static function submit(int $studentId, string $type, string $details, ?string $effectiveDate, ?int $offeringId,
                                  ?int $targetProgramId, ?array $attachment): array
    {
        if (!isset(self::TYPES[$type])) {
            throw new RuntimeException('Choose the type of request.');
        }
        if (mb_strlen(trim($details)) < 15) {
            throw new RuntimeException('Explain your request in a few sentences.');
        }
        if ($type === 'programme_change' && !$targetProgramId) {
            throw new RuntimeException('Choose the programme you want to move to.');
        }
        if ($type === 'grade_appeal' && !$offeringId) {
            throw new RuntimeException('Choose the course whose grade you are appealing.');
        }
        if (Database::scalar('SELECT 1 FROM student_requests WHERE student_id = ? AND request_type = ? AND status IN ("submitted","under_review")', [$studentId, $type])) {
            throw new RuntimeException('You already have a request of this type being considered.');
        }
        $file = $attachment !== null ? \App\Core\Upload::storePrivate($attachment, 'requests', array_merge(\App\Core\Upload::DOCUMENTS, \App\Core\Upload::IMAGES), 10) : null;
        $now = date('Y-m-d H:i:s');
        $ref = 'REQ-' . date('ym') . '-' . strtoupper(bin2hex(random_bytes(3)));
        Database::statement(
            'INSERT INTO student_requests (reference, student_id, request_type, details, effective_date, offering_id, target_program_id,
                                           attachment_path, attachment_name, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$ref, $studentId, $type, trim($details), $effectiveDate ?: null, $offeringId ?: null, $targetProgramId ?: null,
             $file['path'] ?? null, $file['name'] ?? null, $now, $now]
        );
        Notify::toRole('registrar', 'New student request: ' . self::TYPES[$type], $ref, '/requests');
        return ['reference' => $ref, 'id' => Database::lastInsertId()];
    }

    /** review | approve | reject */
    public static function decide(int $id, string $decision, ?string $note, int $userId): string
    {
        $r = Database::selectOne(
            'SELECT r.*, s.user_id, s.admission_number, s.status AS student_status FROM student_requests r JOIN students s ON s.id = r.student_id WHERE r.id = ?',
            [$id]
        ) ?? throw new RuntimeException('Request not found.');
        if (!in_array($r['status'], ['submitted', 'under_review'], true)) {
            throw new RuntimeException('This request has already been decided.');
        }
        $now = date('Y-m-d H:i:s');
        if ($decision === 'review') {
            Database::statement('UPDATE student_requests SET status = "under_review", reviewed_by = ?, updated_at = ? WHERE id = ?', [$userId, $now, $id]);
            return 'Marked as under review.';
        }
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new RuntimeException('Unknown decision.');
        }
        if ($decision === 'reject' && trim((string) $note) === '') {
            throw new RuntimeException('Give the reason for rejecting the request.');
        }

        $effect = '';
        Database::transaction(static function () use ($r, $decision, $note, $userId, $now, &$effect): void {
            Database::statement(
                'UPDATE student_requests SET status = ?, decision_note = ?, reviewed_by = ?, decided_at = ?, updated_at = ? WHERE id = ?',
                [$decision === 'approve' ? 'approved' : 'rejected', trim((string) $note) ?: null, $userId, $now, $now, $r['id']]
            );
            if ($decision !== 'approve') {
                return;
            }
            switch ($r['request_type']) {
                case 'leave_of_absence':
                case 'deferment':
                    Database::statement('UPDATE students SET status = "deferred" WHERE id = ?', [$r['student_id']]);
                    $effect = ' The student is now recorded as deferred.';
                    break;
                case 'withdrawal':
                    Database::statement('UPDATE students SET status = "withdrawn" WHERE id = ?', [$r['student_id']]);
                    $effect = ' The student is now recorded as withdrawn.';
                    break;
                case 'readmission':
                    Database::statement('UPDATE students SET status = "active" WHERE id = ?', [$r['student_id']]);
                    $effect = ' The student is active again.';
                    break;
                case 'programme_change':
                    Database::statement('UPDATE students SET program_id = ? WHERE id = ?', [$r['target_program_id'], $r['student_id']]);
                    $effect = ' The student has been moved to the new programme.';
                    break;
            }
        });
        Audit::log('request_' . $decision, 'requests', 'student_requests', (string) $r['id'], $r['reference'] . ' ' . $decision . 'd.' . $effect);
        Alerts::send((int) $r['user_id'], 'document_issued', 'Your request ' . $r['reference'] . ' was ' . ($decision === 'approve' ? 'approved' : 'not approved'),
            self::TYPES[$r['request_type']] . ($note ? ': ' . $note : '.'), '/portal/requests', $decision === 'approve' ? 'success' : 'warning', 'clipboard');
        return ($decision === 'approve' ? 'Approved.' : 'Rejected.') . $effect;
    }
}
