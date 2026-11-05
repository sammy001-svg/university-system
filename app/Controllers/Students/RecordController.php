<?php
declare(strict_types=1);

namespace App\Controllers\Students;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Academic\DegreeAudit;
use App\Services\Academic\DocumentService;
use RuntimeException;

/**
 * Degree audits and official documents. Staff see any student (students.view;
 * issuing needs students.edit); a student sees their own audit and documents
 * and may issue their own enrolment letter once registration is complete.
 */
final class RecordController extends Controller
{
    /* ---------------- Degree audit ---------------- */

    public function audit(Request $request, string $id): string
    {
        $this->authorize('students.view');
        return $this->renderAudit((int) $id, '/students/' . $id);
    }

    public function myAudit(Request $request): string
    {
        return $this->renderAudit($this->myStudentId(), '/portal');
    }

    private function renderAudit(int $studentId, string $backUrl): string
    {
        $audit = DegreeAudit::for($studentId);
        if ($audit === null) {
            throw new HttpException(404, 'Student not found.');
        }
        return $this->view('records.audit', ['pageTitle' => 'Degree audit', 'audit' => $audit, 'backUrl' => $backUrl]);
    }

    /* ---------------- Documents ---------------- */

    public function documents(Request $request, string $id): string
    {
        $this->authorize('students.view');
        $student = DocumentService::student((int) $id) ?? throw new HttpException(404, 'Student not found.');
        return $this->view('records.documents', [
            'pageTitle' => 'Documents - ' . $student['admission_number'],
            'student'   => $student,
            'documents' => $this->issued((int) $id),
            'types'     => DocumentService::TYPES,
            'canIssue'  => Auth::can('students.edit'),
            'self'      => false,
        ]);
    }

    public function issue(Request $request, string $id): never
    {
        $this->authorize('students.edit');
        $this->verifyCsrf($request);
        try {
            $doc = DocumentService::issue((string) $request->input('doc_type'), (int) $id, (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/students/' . $id . '/documents');
        }
        $this->success('Issued ' . $doc['serial'] . '. Print it from the list.', '/students/' . $id . '/documents');
    }

    public function myDocuments(Request $request): string
    {
        $studentId = $this->myStudentId();
        return $this->view('records.documents', [
            'pageTitle' => 'My documents',
            'student'   => DocumentService::student($studentId),
            'documents' => $this->issued($studentId),
            'types'     => ['enrollment_letter' => DocumentService::TYPES['enrollment_letter']],
            'canIssue'  => true,
            'self'      => true,
        ]);
    }

    public function issueMine(Request $request): never
    {
        $this->verifyCsrf($request);
        try {
            $doc = DocumentService::issue('enrollment_letter', $this->myStudentId(), (int) Auth::id());
        } catch (RuntimeException $e) {
            $this->error($e->getMessage(), '/portal/documents');
        }
        $this->redirect('/issued-documents/' . $doc['id']);
    }

    /** The printable document (staff with students.view, or the student it belongs to). */
    public function print(Request $request, string $id): string
    {
        $doc = DocumentService::find((int) $id) ?? throw new HttpException(404, 'Document not found.');
        $student = DocumentService::student((int) $doc['student_id']);
        if (!Auth::can('students.view') && (int) $student['user_id'] !== (int) Auth::id()) {
            throw new HttpException(403);
        }
        return $this->view('records.print', [
            'pageTitle' => DocumentService::TYPES[$doc['doc_type']] . ' ' . $doc['serial'],
            'doc'       => $doc,
            'student'   => $student,
            'summary'   => json_decode((string) $doc['summary'], true) ?: [],
            'terms'     => $doc['doc_type'] === 'transcript' ? DocumentService::transcript((int) $doc['student_id']) : [],
            'verifyUrl' => url('/verify/' . $doc['verification_code']),
            'signatory' => Database::selectOne('SELECT CONCAT_WS(" ", title, first_name, last_name) AS name FROM users WHERE id = ?', [$doc['issued_by']])['name'] ?? null,
        ]);
    }

    public function revoke(Request $request, string $id): never
    {
        $this->authorize('students.edit');
        $this->verifyCsrf($request);
        $doc = DocumentService::find((int) $id) ?? throw new HttpException(404);
        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            $this->error('Give the reason for revoking the document.', '/students/' . $doc['student_id'] . '/documents');
        }
        DocumentService::revoke((int) $id, $reason, (int) Auth::id());
        $this->success('Document ' . $doc['serial'] . ' revoked; its QR code now shows it as revoked.', '/students/' . $doc['student_id'] . '/documents');
    }

    /* ---------------- Public verification ---------------- */

    public function verify(Request $request, ?string $code = null): string
    {
        $code = $code ?? (string) $request->query('code', '');
        $doc  = $code !== '' ? DocumentService::verify($code) : null;
        return $this->view('records.verify', [
            'pageTitle' => 'Verify a document',
            'code'      => $code,
            'doc'       => $doc,
            'summary'   => $doc ? (json_decode((string) $doc['summary'], true) ?: []) : [],
        ]);
    }

    private function issued(int $studentId): array
    {
        return Database::select(
            'SELECT d.*, CONCAT_WS(" ", u.first_name, u.last_name) AS issued_by_name FROM issued_documents d
          LEFT JOIN users u ON u.id = d.issued_by WHERE d.student_id = ? ORDER BY d.issued_at DESC',
            [$studentId]
        );
    }

    private function myStudentId(): int
    {
        $id = Database::scalar('SELECT id FROM students WHERE user_id = ?', [Auth::id()]);
        if ($id === null) {
            throw new HttpException(403, 'No student profile is linked to your account.');
        }
        return (int) $id;
    }
}
