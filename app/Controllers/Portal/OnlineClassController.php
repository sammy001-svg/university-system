<?php
declare(strict_types=1);

namespace App\Controllers\Portal;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Models\Semester;
use App\Services\Elearning\ClassroomService;
use App\Services\Elearning\CourseAccess;

/** The student's online classes across their registered courses, and joining them. */
final class OnlineClassController extends Controller
{
    use CurrentStudent;

    public function index(Request $request): string
    {
        $student  = $this->student();
        $semester = (new Semester())->current();
        $sessions = ClassroomService::sessions(
            CourseAccess::studiedOfferingIds((int) $student['id'], $semester['id'] ?? null),
            (int) $student['id']
        );

        $groups = ['live' => [], 'upcoming' => [], 'past' => []];
        foreach ($sessions as $session) {
            $session['phase']    = ClassroomService::phase($session);
            $session['can_join'] = ClassroomService::canJoin($session);
            $key = match ($session['phase']) { 'live' => 'live', 'upcoming' => 'upcoming', default => 'past' };
            $groups[$key][] = $session;
        }
        $groups['past'] = array_reverse($groups['past']);

        $held     = array_filter($groups['past'], static fn (array $s): bool => $s['phase'] === 'ended');
        $attended = array_filter($held, static fn (array $s): bool => $s['joined_at'] !== null);

        return $this->view('portal.classes', [
            'pageTitle' => 'Online Classes',
            'student'   => $student,
            'semester'  => $semester,
            'groups'    => $groups,
            'rate'      => $held === [] ? null : round(count($attended) / count($held) * 100),
        ]);
    }

    /** Record attendance and send the student to the meeting. */
    public function join(Request $request, string $id): never
    {
        $student = $this->student();
        $session = Database::selectOne('SELECT * FROM class_sessions WHERE id = ?', [(int) $id]);
        if ($session === null) {
            throw new HttpException(404, 'Online class not found.');
        }
        CourseAccess::studying((int) $session['offering_id'], (int) $student['id']);
        if (!ClassroomService::canJoin($session)) {
            $phase = ClassroomService::phase($session);
            $this->error(match ($phase) {
                'cancelled' => 'This class was cancelled.',
                'ended'     => 'This class has ended.' . ($session['recording_url'] ? ' Its recording is on the course page.' : ''),
                default     => 'You can join from ' . ClassroomService::EARLY_JOIN_MINUTES . ' minutes before the start (' . fdatetime($session['starts_at']) . ').',
            }, '/portal/classes');
        }
        ClassroomService::recordJoin((int) $session['id'], (int) $student['id']);
        $this->redirect($session['meeting_url']);
    }
}
