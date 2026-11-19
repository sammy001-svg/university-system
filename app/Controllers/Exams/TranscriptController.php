<?php
declare(strict_types=1);

namespace App\Controllers\Exams;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;

final class TranscriptController extends Controller
{
    public function index(Request $request): string
    {
        $this->authorize('results.view');
        $students = Database::select(
            'SELECT s.id, s.admission_number, u.first_name, u.last_name, p.name AS program_name, s.cgpa
               FROM students s
               JOIN users u    ON u.id = s.user_id
               JOIN programs p ON p.id = s.program_id
              ORDER BY u.last_name, u.first_name'
        );
        return $this->view('exams.transcripts.index', ['pageTitle' => 'Transcripts', 'students' => $students]);
    }
}
