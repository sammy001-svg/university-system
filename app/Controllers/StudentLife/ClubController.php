<?php
declare(strict_types=1);

namespace App\Controllers\StudentLife;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Exceptions\HttpException;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\ResourceController;
use App\Models\Club;
use App\Support\Lookup;

/** Clubs and societies: registration, patrons, members and officers; students join from the portal. */
final class ClubController extends ResourceController
{
    protected string $title = 'Clubs & Societies';
    protected string $entity = 'Club';
    protected string $routeBase = '/clubs';
    protected string $permission = 'clubs';
    protected bool $viewManagePermissions = true;
    protected string $icon = 'users';
    protected string $lede = 'Registered student clubs, associations and societies, with their patrons and officers.';
    protected string $defaultSort = 'name';
    protected string $defaultDir = 'asc';

    public const CATEGORIES = ['academic', 'cultural', 'sports', 'religious', 'professional', 'community_service', 'media', 'other'];
    public const ROLES = ['member', 'president', 'vice_president', 'secretary', 'treasurer', 'officer'];

    public function __construct()
    {
        $this->model = new Club();
    }

    protected function listQuery(Request $request): QueryBuilder
    {
        $q = $this->model->query('c')
            ->select('c.*', 'CONCAT(u.first_name, " ", u.last_name) AS patron_name', '(SELECT COUNT(*) FROM club_members m WHERE m.club_id = c.id) AS members')
            ->leftJoin('staff st', 'st.id = c.patron_id')
            ->leftJoin('users u', 'u.id = st.user_id');
        if ($request->filled('category')) {
            $q->where('c.category', $request->query('category'));
        }
        return $q;
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Club / society', 'type' => 'strong'],
            ['key' => 'category', 'label' => 'Category', 'type' => 'humanize'],
            ['key' => 'patron_name', 'label' => 'Patron'],
            ['key' => 'meeting_schedule', 'label' => 'Meets'],
            ['key' => 'members', 'label' => 'Members', 'type' => 'number', 'align' => 'center'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function filters(): array
    {
        return [['name' => 'category', 'label' => 'Category', 'options' => array_combine(self::CATEGORIES, array_map('humanize', self::CATEGORIES))]];
    }

    protected function rowActions(): array
    {
        return [['label' => 'Members', 'url' => '/clubs/{id}/members', 'icon' => 'users']];
    }

    protected function fields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'width' => 8, 'required' => true],
            ['name' => 'category', 'label' => 'Category', 'type' => 'enum', 'width' => 4, 'required' => true, 'values' => self::CATEGORIES],
            ['name' => 'patron_id', 'label' => 'Staff patron', 'type' => 'select', 'width' => 6, 'options' => Lookup::staff()],
            ['name' => 'meeting_schedule', 'label' => 'Meetings', 'width' => 6, 'attrs' => 'placeholder="e.g. Wednesdays 4pm, Room B12"'],
            ['name' => 'description', 'label' => 'About the club', 'type' => 'textarea', 'width' => 12, 'rows' => 4],
            ['name' => 'open_to_join', 'label' => 'Students can join from the portal', 'type' => 'checkbox', 'width' => 6, 'default' => 1],
            ['name' => 'status', 'label' => 'Status', 'type' => 'enum', 'width' => 6, 'required' => true, 'values' => ['active', 'inactive']],
        ];
    }

    protected function rules(?int $id): array
    {
        return ['name' => 'required|max:150|unique:clubs,name,' . ($id ?? ''), 'category' => 'required|in:' . implode(',', self::CATEGORIES),
                'patron_id' => 'nullable|integer', 'meeting_schedule' => 'nullable|max:150', 'description' => 'nullable',
                'open_to_join' => 'nullable|integer', 'status' => 'required|in:active,inactive'];
    }

    protected function beforeSave(array $data, Request $request, ?int $id): array
    {
        $data['open_to_join'] = $request->input('open_to_join') ? 1 : 0;
        return $data;
    }

    public function members(Request $request, string $id): string
    {
        $this->permit('view');
        $club = $this->findOrFail($this->model, (int) $id, 'Club');
        return $this->view('clubs.members', [
            'pageTitle' => $club['name'],
            'club'      => $club,
            'rows'      => Database::select(
                'SELECT m.*, s.admission_number, u.first_name, u.last_name, u.phone, u.email, p.name AS program_name
                   FROM club_members m JOIN students s ON s.id = m.student_id JOIN users u ON u.id = s.user_id JOIN programs p ON p.id = s.program_id
                  WHERE m.club_id = ? ORDER BY m.role = "member", m.role, u.last_name', [(int) $id]),
            'students'  => (new \App\Models\Student())->options(),
            'canManage' => $this->allowed('manage'),
        ]);
    }

    public function saveMember(Request $request, string $id): never
    {
        $this->permit('edit');
        $this->verifyCsrf($request);
        $role = in_array($request->input('role'), self::ROLES, true) ? (string) $request->input('role') : 'member';
        $student = (int) $request->input('student_id');
        if ($request->input('remove') === '1') {
            Database::statement('DELETE FROM club_members WHERE club_id = ? AND student_id = ?', [(int) $id, $student]);
            $this->success('Member removed.', '/clubs/' . $id . '/members');
        }
        if ($role !== 'member' && $role !== 'officer') {
            // One president, one treasurer ... per club.
            Database::statement('UPDATE club_members SET role = "member" WHERE club_id = ? AND role = ?', [(int) $id, $role]);
        }
        Database::statement('INSERT INTO club_members (club_id, student_id, role, joined_on) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)',
            [(int) $id, $student, $role, date('Y-m-d')]);
        $this->success('Membership saved.', '/clubs/' . $id . '/members');
    }

    /* ---------------- portal ---------------- */

    public function portal(Request $request): string
    {
        $studentId = $this->myStudentId();
        return $this->view('clubs.portal', [
            'pageTitle' => 'Clubs & Societies',
            'clubs'     => Database::select(
                'SELECT c.*, CONCAT(u.first_name, " ", u.last_name) AS patron_name, (SELECT COUNT(*) FROM club_members m WHERE m.club_id = c.id) AS members,
                        (SELECT m.role FROM club_members m WHERE m.club_id = c.id AND m.student_id = ?) AS my_role
                   FROM clubs c LEFT JOIN staff st ON st.id = c.patron_id LEFT JOIN users u ON u.id = st.user_id
                  WHERE c.status = "active" ORDER BY my_role IS NULL, c.category, c.name', [$studentId]),
        ]);
    }

    public function join(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        $studentId = $this->myStudentId();
        $club = Database::selectOne('SELECT * FROM clubs WHERE id = ? AND status = "active"', [(int) $id]) ?? throw new HttpException(404);
        if ($request->input('leave') === '1') {
            Database::statement('DELETE FROM club_members WHERE club_id = ? AND student_id = ? AND role = "member"', [$club['id'], $studentId]);
            $this->success('You have left ' . $club['name'] . '.', '/portal/clubs');
        }
        if ((int) $club['open_to_join'] !== 1) {
            $this->error('Membership of ' . $club['name'] . ' is by invitation; speak to the patron.', '/portal/clubs');
        }
        Database::statement('INSERT IGNORE INTO club_members (club_id, student_id, role, joined_on) VALUES (?, ?, "member", ?)', [$club['id'], $studentId, date('Y-m-d')]);
        $this->success('Welcome to ' . $club['name'] . '!', '/portal/clubs');
    }

    private function myStudentId(): int
    {
        return (int) (Database::scalar('SELECT id FROM students WHERE user_id = ?', [Auth::id()]) ?: throw new HttpException(403, 'No student profile is linked to your account.'));
    }
}
