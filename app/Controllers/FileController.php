<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Services\Elearning\CourseAccess;
use App\Support\PrivateFile;

/** Course files, served only to the class's lecturers and the students registered on it. */
final class FileController extends Controller
{
    public function material(Request $request, string $id): never
    {
        $m = Database::selectOne('SELECT * FROM course_materials WHERE id = ? AND kind = "file"', [(int) $id]);
        if ($m === null) {
            throw new HttpException(404);
        }
        $teacher = CourseAccess::mayTeach((int) $m['offering_id']);
        if (!$teacher && ((int) $m['is_published'] !== 1 || CourseAccess::studentOn((int) $m['offering_id']) === null)) {
            throw new HttpException(403, 'This file is for the students of the class.');
        }
        PrivateFile::send($m['file_path'], $m['file_name']);
    }

    public function assignmentBrief(Request $request, string $id): never
    {
        $a = Database::selectOne('SELECT * FROM assignments WHERE id = ?', [(int) $id]);
        if ($a === null || !$a['attachment_path']) {
            throw new HttpException(404);
        }
        if (!CourseAccess::mayTeach((int) $a['offering_id']) && CourseAccess::studentOn((int) $a['offering_id']) === null) {
            throw new HttpException(403, 'This file is for the students of the class.');
        }
        PrivateFile::send($a['attachment_path'], $a['attachment_name']);
    }

    /** A student's submitted work: the student themself and the class's lecturers. */
    public function submission(Request $request, string $id): never
    {
        $sub = Database::selectOne(
            'SELECT sub.*, a.offering_id, s.user_id, s.admission_number FROM assignment_submissions sub
               JOIN assignments a ON a.id = sub.assignment_id JOIN students s ON s.id = sub.student_id WHERE sub.id = ?',
            [(int) $id]
        );
        if ($sub === null || !$sub['file_path']) {
            throw new HttpException(404);
        }
        if ((int) $sub['user_id'] !== (int) \App\Core\Auth::id() && !CourseAccess::mayTeach((int) $sub['offering_id'])) {
            throw new HttpException(403);
        }
        PrivateFile::send($sub['file_path'], str_replace('/', '-', $sub['admission_number']) . ' - ' . $sub['file_name']);
    }
}
