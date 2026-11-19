<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Academic\AcademicRecord;

/**
 * The two printed records of the Office of Admission and Records: a semester's grade
 * report ("student records") and the transcript of academic record. Staff reach them
 * from a student's profile; a student reaches their own from the portal.
 */
final class AcademicRecordController extends Controller
{
    /** One semester's grade report, for staff. */
    public function record(Request $request, string $id): string
    {
        $this->authorize(['students.view', 'results.view']);
        return $this->renderRecord($request, (int) $id, '/students/' . (int) $id . '/records', [
            'backUrl'       => url('/students/' . (int) $id),
            'transcriptUrl' => url('/students/' . (int) $id . '/transcript'),
        ]);
    }

    /** The full transcript, for staff. */
    public function transcript(Request $request, string $id): string
    {
        $this->authorize(['students.view', 'results.view']);
        return $this->renderTranscript((int) $id, [
            'backUrl'   => url('/students/' . (int) $id),
            'recordUrl' => url('/students/' . (int) $id . '/records'),
        ]);
    }

    /** A student's own grade report. */
    public function myRecord(Request $request): string
    {
        $studentId = $this->ownStudentId();
        return $this->renderRecord($request, $studentId, '/portal/records', [
            'backUrl'       => url('/portal/results'),
            'transcriptUrl' => url('/portal/transcript'),
        ]);
    }

    /** A student's own transcript. */
    public function myTranscript(Request $request): string
    {
        return $this->renderTranscript($this->ownStudentId(), [
            'backUrl'   => url('/portal/results'),
            'recordUrl' => url('/portal/records'),
        ]);
    }

    private function renderRecord(Request $request, int $studentId, string $basePath, array $links): string
    {
        $student   = $this->mustFind($studentId);
        $semesters = AcademicRecord::semestersWithResults($studentId);

        $semesterId = $request->int('semester_id');
        $ids        = array_map('intval', array_column($semesters, 'id'));
        if (!in_array($semesterId, $ids, true)) {
            $semesterId = $ids[0] ?? 0;   // the latest semester with results
        }

        return $this->view('records.semester-record', $links + [
            'pageTitle'  => 'Student Records - ' . AcademicRecord::formalName($student),
            'student'    => $student,
            'semesters'  => $semesters,
            'semesterId' => $semesterId,
            'baseUrl'    => url($basePath),
            'record'     => $semesterId > 0 ? AcademicRecord::semester($studentId, $semesterId) : null,
        ]);
    }

    private function renderTranscript(int $studentId, array $links): string
    {
        $student = $this->mustFind($studentId);

        return $this->view('records.transcript', $links + [
            'pageTitle' => 'Transcript - ' . AcademicRecord::formalName($student),
            'student'   => $student,
            'tx'        => AcademicRecord::transcript($studentId),
        ]);
    }

    private function mustFind(int $studentId): array
    {
        $student = AcademicRecord::student($studentId);
        if ($student === null) {
            throw new HttpException(404, 'Student not found.');
        }
        return $student;
    }

    private function ownStudentId(): int
    {
        $id = (int) Database::scalar('SELECT id FROM students WHERE user_id = ?', [(int) Auth::id()]);
        if ($id === 0) {
            throw new HttpException(403, 'No student profile is linked to your account.');
        }
        return $id;
    }
}
