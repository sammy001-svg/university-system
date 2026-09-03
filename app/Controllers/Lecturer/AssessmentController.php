<?php
declare(strict_types=1);

namespace App\Controllers\Lecturer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Assessment;
use App\Models\AssessmentScore;

final class AssessmentController extends Controller
{
    public function index(Request $request, string $id): string
    {
        $this->authorize('teaching.access');
        $assessments = Database::select(
            'SELECT * FROM assessments WHERE offering_id = ? ORDER BY due_date',
            [(int) $id]
        );
        $offering = $this->offering((int) $id);

        return $this->view('lecturer.assessments.index', [
            'pageTitle'   => 'Assessments – ' . $offering['code'],
            'offering'    => $offering,
            'assessments' => $assessments,
        ]);
    }

    public function store(Request $request, string $id): never
    {
        $this->authorize('results.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'title'    => 'required|max:150',
            'type'     => 'required|in:cat,assignment,quiz,practical,project,presentation,final_exam',
            'max_score'=> 'required|numeric',
            'weight'   => 'required|numeric',
            'due_date' => 'nullable|date',
        ]);
        $data['offering_id'] = (int) $id;
        $data['created_by']  = Auth::id();
        (new Assessment())->create($data);
        $this->success('Assessment created.', '/teaching/' . $id . '/assessments');
    }

    public function scores(Request $request, string $id): string
    {
        $this->authorize('results.create');
        $assessment = Database::selectOne('SELECT a.*, o.id AS offering_id, c.code FROM assessments a JOIN course_offerings o ON o.id = a.offering_id JOIN courses c ON c.id = o.course_id WHERE a.id = ?', [(int) $id]);
        if (!$assessment) { throw new HttpException(404); }

        $students = Database::select(
            'SELECT cr.student_id, s.admission_number, u.first_name, u.last_name,
                    ascore.id AS score_id, ascore.score, ascore.status AS score_status
               FROM course_registrations cr
               JOIN students s ON s.id = cr.student_id
               JOIN users u    ON u.id = s.user_id
          LEFT JOIN assessment_scores ascore ON ascore.assessment_id = ? AND ascore.student_id = cr.student_id
              WHERE cr.offering_id = ? AND cr.status = "registered"
              ORDER BY u.last_name, u.first_name',
            [(int) $id, $assessment['offering_id']]
        );

        return $this->view('lecturer.assessments.scores', [
            'pageTitle'  => 'Scores – ' . $assessment['title'],
            'assessment' => $assessment,
            'students'   => $students,
        ]);
    }

    public function saveScores(Request $request, string $id): never
    {
        $this->authorize('results.create');
        $this->verifyCsrf($request);
        $scores = $request->input('scores', []);
        foreach ((array) $scores as $studentId => $score) {
            $existing = Database::scalar('SELECT id FROM assessment_scores WHERE assessment_id=? AND student_id=?', [(int)$id, (int)$studentId]);
            if ($existing) {
                Database::statement('UPDATE assessment_scores SET score=?, status="graded", graded_by=?, graded_at=NOW() WHERE id=?', [(float)$score, Auth::id(), (int)$existing]);
            } else {
                Database::statement('INSERT INTO assessment_scores (assessment_id, student_id, score, status, graded_by, graded_at) VALUES (?,?,?,"graded",?,NOW())', [(int)$id, (int)$studentId, (float)$score, Auth::id()]);
            }
        }
        $assessment = Database::selectOne('SELECT offering_id FROM assessments WHERE id=?', [(int)$id]);
        $this->success('Scores saved.', '/teaching/assessments/' . $id . '/scores');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('results.delete');
        $this->verifyCsrf($request);
        $assessment = Database::selectOne('SELECT offering_id FROM assessments WHERE id=?', [(int)$id]);
        (new Assessment())->delete((int) $id);
        $this->success('Assessment deleted.', '/teaching/' . ($assessment['offering_id'] ?? '') . '/assessments');
    }

    private function offering(int $id): array
    {
        $row = Database::selectOne('SELECT o.*, c.code, c.title FROM course_offerings o JOIN courses c ON c.id = o.course_id WHERE o.id = ?', [$id]);
        if (!$row) { throw new HttpException(404); }
        return $row;
    }
}
