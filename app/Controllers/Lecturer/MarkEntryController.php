<?php
declare(strict_types=1);

namespace App\Controllers\Lecturer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;

final class MarkEntryController extends Controller
{
    public function edit(Request $request, string $id): string
    {
        $this->authorize('results.create');
        $offering = $this->offering((int) $id);

        $students = Database::select(
            'SELECT cr.student_id, s.admission_number, u.first_name, u.last_name,
                    COALESCE(res.coursework_score, 0) AS coursework_score,
                    COALESCE(res.exam_score, 0)       AS exam_score,
                    res.id AS result_id, res.is_published
               FROM course_registrations cr
               JOIN students s ON s.id = cr.student_id
               JOIN users u    ON u.id = s.user_id
          LEFT JOIN course_results res ON res.student_id = cr.student_id AND res.offering_id = cr.offering_id
              WHERE cr.offering_id = ? AND cr.status = "registered"
              ORDER BY u.last_name, u.first_name',
            [(int) $id]
        );

        return $this->view('lecturer.marks.edit', [
            'pageTitle' => 'Mark Entry – ' . $offering['code'],
            'offering'  => $offering,
            'students'  => $students,
        ]);
    }

    public function save(Request $request, string $id): never
    {
        $this->authorize('results.create');
        $this->verifyCsrf($request);
        $offering = $this->offering((int) $id);

        $marks = $request->input('marks', []);
        if (!is_array($marks)) { $this->error('Invalid data.', '/teaching/' . $id . '/marks'); }

        foreach ($marks as $studentId => $scores) {
            $cw   = (float) ($scores['coursework_score'] ?? 0);
            $exam = (float) ($scores['exam_score'] ?? 0);
            $total = ($cw * $offering['coursework_weight'] / 100) + ($exam * $offering['exam_weight'] / 100);

            $existing = Database::scalar(
                'SELECT id FROM course_results WHERE student_id = ? AND offering_id = ?',
                [(int) $studentId, (int) $id]
            );

            $data = [
                'coursework_score' => $cw,
                'exam_score'       => $exam,
                'total_score'      => $total,
                'entered_by'       => Auth::id(),
            ];

            if ($existing) {
                Database::statement(
                    'UPDATE course_results SET coursework_score=?, exam_score=?, total_score=?, entered_by=?, updated_at=NOW() WHERE id=?',
                    [$cw, $exam, $total, Auth::id(), (int) $existing]
                );
            } else {
                Database::statement(
                    'INSERT INTO course_results (student_id, offering_id, semester_id, course_id, coursework_score, exam_score, total_score, credit_hours, attempt, entered_by)
                     SELECT ?, ?, o.semester_id, o.course_id, ?, ?, ?, c.credit_hours, 1, ?
                       FROM course_offerings o JOIN courses c ON c.id = o.course_id WHERE o.id = ?',
                    [(int) $studentId, (int) $id, $cw, $exam, $total, Auth::id(), (int) $id]
                );
            }
        }

        $this->success('Marks saved successfully.', '/teaching/' . $id . '/marks');
    }

    public function publish(Request $request, string $id): never
    {
        $this->authorize('results.publish');
        $this->verifyCsrf($request);

        Database::statement(
            'UPDATE course_results SET is_published = 1, published_at = NOW() WHERE offering_id = ?',
            [(int) $id]
        );
        $this->success('Results published.', '/teaching/' . $id . '/marks');
    }

    private function offering(int $id): array
    {
        $row = Database::selectOne(
            'SELECT o.*, c.code, c.title, o.coursework_weight, o.exam_weight
               FROM course_offerings o JOIN courses c ON c.id = o.course_id WHERE o.id = ?',
            [$id]
        );
        if (!$row) { throw new HttpException(404); }
        return $row;
    }
}
