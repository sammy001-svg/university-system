<?php
declare(strict_types=1);

namespace App\Services\Elearning;

use App\Core\Database;
use App\Core\Upload;
use App\Services\Alerts\Alerts;
use RuntimeException;

/**
 * Assignments submitted online: deadlines, late work, marking and the
 * transfer of marks to the course gradebook. Times are compared in PHP.
 */
final class AssignmentService
{
    public const SUBMISSION_TYPES = ['file' => 'File upload', 'text' => 'Typed answer', 'both' => 'File and/or typed answer'];
    public const FILE_TYPES       = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'zip'];
    public const MAX_MB           = 20;

    public static function find(int $id): ?array
    {
        return Database::selectOne('SELECT * FROM assignments WHERE id = ?', [$id]);
    }

    /** draft | open | late (past due, late work accepted) | closed */
    public static function phase(array $assignment): string
    {
        if ($assignment['status'] === 'draft') {
            return 'draft';
        }
        if (date('Y-m-d H:i:s') <= $assignment['due_at']) {
            return 'open';
        }
        return (int) $assignment['allow_late'] === 1 ? 'late' : 'closed';
    }

    public static function submission(int $assignmentId, int $studentId): ?array
    {
        return Database::selectOne('SELECT * FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?', [$assignmentId, $studentId]);
    }

    /** Hand in (or replace, until it is marked) the student's work. */
    public static function submit(array $assignment, int $studentId, ?array $file, ?string $text): void
    {
        $phase = self::phase($assignment);
        if ($phase === 'draft' || $phase === 'closed') {
            throw new RuntimeException($phase === 'closed' ? 'The deadline has passed and late work is not accepted.' : 'This assignment is not open.');
        }
        $existing = self::submission((int) $assignment['id'], $studentId);
        if ($existing !== null && $existing['graded_at'] !== null) {
            throw new RuntimeException('Your work has already been marked, so it can no longer be changed.');
        }
        $text = trim((string) $text);
        $type = $assignment['submission_type'];
        $hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($type === 'file' && !$hasFile && $existing === null) {
            throw new RuntimeException('Choose the file to upload.');
        }
        if ($type === 'text' && $text === '') {
            throw new RuntimeException('Type your answer before submitting.');
        }
        if ($type === 'both' && !$hasFile && $text === '' && $existing === null) {
            throw new RuntimeException('Upload a file or type an answer.');
        }

        $stored = null;
        if ($hasFile && $type !== 'text') {
            $stored = Upload::storePrivate($file, 'submissions/' . (int) $assignment['id'], self::FILE_TYPES, self::MAX_MB);
        }
        $now  = date('Y-m-d H:i:s');
        $late = $now > $assignment['due_at'] ? 1 : 0;

        if ($existing === null) {
            Database::statement(
                'INSERT INTO assignment_submissions (assignment_id, student_id, file_path, file_name, file_size, text_answer, submitted_at, is_late)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$assignment['id'], $studentId, $stored['path'] ?? null, $stored['name'] ?? null, $stored['size'] ?? null,
                 $text !== '' ? $text : null, $now, $late]
            );
            return;
        }
        if ($stored !== null && $existing['file_path']) {
            Upload::delete($existing['file_path']);
        }
        Database::statement(
            'UPDATE assignment_submissions SET file_path = COALESCE(?, file_path), file_name = COALESCE(?, file_name), file_size = COALESCE(?, file_size),
                    text_answer = ?, submitted_at = ?, is_late = ?, versions = versions + 1
              WHERE id = ?',
            [$stored['path'] ?? null, $stored['name'] ?? null, $stored['size'] ?? null,
             $text !== '' ? $text : ($type === 'text' ? null : $existing['text_answer']), $now, $late, $existing['id']]
        );
    }

    /** Mark a submission; a late one loses the assignment's late penalty. */
    public static function grade(array $assignment, int $submissionId, float $rawScore, ?string $feedback, int $graderId): float
    {
        $submission = Database::selectOne(
            'SELECT sub.*, s.user_id FROM assignment_submissions sub JOIN students s ON s.id = sub.student_id WHERE sub.id = ? AND sub.assignment_id = ?',
            [$submissionId, $assignment['id']]
        );
        if ($submission === null) {
            throw new RuntimeException('Submission not found.');
        }
        $raw   = max(0, min((float) $assignment['max_score'], $rawScore));
        $score = (int) $submission['is_late'] === 1 ? round($raw * (100 - (int) $assignment['late_penalty']) / 100, 2) : $raw;
        $first = $submission['graded_at'] === null;
        Database::statement(
            'UPDATE assignment_submissions SET raw_score = ?, score = ?, feedback = ?, graded_by = ?, graded_at = ? WHERE id = ?',
            [$raw, $score, $feedback !== null && trim($feedback) !== '' ? trim($feedback) : null, $graderId, date('Y-m-d H:i:s'), $submissionId]
        );
        if ($first) {
            Alerts::send((int) $submission['user_id'], 'assignment_graded', 'Assignment marked: ' . $assignment['title'],
                'You scored ' . rtrim(rtrim(number_format($score, 2), '0'), '.') . ' out of ' . rtrim(rtrim((string) $assignment['max_score'], '0'), '.') . '.',
                '/portal/assignments/' . $assignment['id'], 'success', 'check');
        }
        return $score;
    }

    /**
     * Copy marks into the course gradebook as an assessment (created on the first transfer).
     * Work not handed in counts as missed (zero) once the deadline has passed and either late work
     * is not accepted or the lecturer chooses to close it ($missingAsZero).
     * @return array{transferred:int, missed:int, pending:int}
     */
    public static function transferToGradebook(array $assignment, float $weight, int $userId, bool $missingAsZero = false): array
    {
        return Database::transaction(static function () use ($assignment, $weight, $userId, $missingAsZero): array {
            $assessmentId = (int) ($assignment['assessment_id'] ?? 0);
            if ($assessmentId === 0 || !Database::scalar('SELECT 1 FROM assessments WHERE id = ?', [$assessmentId])) {
                Database::statement(
                    'INSERT INTO assessments (offering_id, title, type, max_score, weight, due_date, instructions, is_published, created_by)
                     VALUES (?, ?, "assignment", ?, ?, ?, ?, 1, ?)',
                    [$assignment['offering_id'], $assignment['title'], $assignment['max_score'], $weight, $assignment['due_at'],
                     'Online assignment - marks transferred from the assignment page.', $userId]
                );
                $assessmentId = Database::lastInsertId();
                Database::statement('UPDATE assignments SET assessment_id = ? WHERE id = ?', [$assessmentId, $assignment['id']]);
            } else {
                Database::statement('UPDATE assessments SET max_score = ?, weight = ? WHERE id = ?', [$assignment['max_score'], $weight, $assessmentId]);
            }
            $pastDue = self::phase($assignment) !== 'open';
            $now    = date('Y-m-d H:i:s');
            $counts = ['transferred' => 0, 'missed' => 0, 'pending' => 0];
            foreach (Database::select(
                'SELECT cr.student_id, sub.id AS submission_id, sub.score, sub.submitted_at, sub.graded_at
                   FROM course_registrations cr
              LEFT JOIN assignment_submissions sub ON sub.student_id = cr.student_id AND sub.assignment_id = ?
                  WHERE cr.offering_id = ? AND cr.status <> "dropped"',
                [$assignment['id'], $assignment['offering_id']]
            ) as $row) {
                if ($row['graded_at'] !== null) {
                    Database::statement(
                        'INSERT INTO assessment_scores (assessment_id, student_id, score, submitted_at, graded_by, graded_at, status)
                         VALUES (?, ?, ?, ?, ?, ?, "graded")
                         ON DUPLICATE KEY UPDATE score = VALUES(score), submitted_at = VALUES(submitted_at), graded_by = VALUES(graded_by),
                                                 graded_at = VALUES(graded_at), status = "graded"',
                        [$assessmentId, $row['student_id'], $row['score'], $row['submitted_at'], $userId, $now]
                    );
                    $counts['transferred']++;
                } elseif ($row['submission_id'] === null && $pastDue && ($missingAsZero || (int) $assignment['allow_late'] === 0)) {
                    Database::statement(
                        'INSERT INTO assessment_scores (assessment_id, student_id, score, graded_by, graded_at, status) VALUES (?, ?, 0, ?, ?, "missed")
                         ON DUPLICATE KEY UPDATE score = IF(status = "graded", score, 0), status = IF(status = "graded", status, "missed")',
                        [$assessmentId, $row['student_id'], $userId, $now]
                    );
                    $counts['missed']++;
                } else {
                    $counts['pending']++;
                }
            }
            return $counts;
        });
    }
}
