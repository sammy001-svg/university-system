<?php
declare(strict_types=1);

namespace App\Controllers\Comms;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Models\Announcement;

final class AnnouncementController extends Controller
{
    /** Audiences the signed-in user belongs to. Editors (announcements.create) see everything, drafts included. */
    private function audiences(): array
    {
        $type = Auth::user()['user_type'] ?? '';
        $out  = ['all'];
        if ($type === 'student') { $out[] = 'students'; }
        if (in_array($type, ['staff', 'lecturer', 'admin'], true)) { $out[] = 'staff'; }
        if ($type === 'lecturer') { $out[] = 'lecturers'; }
        return $out;
    }

    public function index(Request $request): string
    {
        $this->authorize('announcements.view');
        $sql = 'SELECT a.*, u.first_name, u.last_name FROM announcements a LEFT JOIN users u ON u.id = a.created_by';
        $params = [];
        if (!Auth::can('announcements.create')) {
            $audiences = $this->audiences();
            $sql .= ' WHERE a.status = "published" AND a.audience IN (' . implode(',', array_fill(0, count($audiences), '?')) . ')
                      AND (a.expires_at IS NULL OR a.expires_at >= ?)';
            $params = array_merge($audiences, [date('Y-m-d H:i:s')]);
        }
        $announcements = Database::select($sql . ' ORDER BY a.published_at DESC LIMIT 100', $params);
        return $this->view('comms.announcements.index', ['pageTitle' => 'Announcements', 'announcements' => $announcements]);
    }

    public function show(Request $request, string $id): string
    {
        $a = Database::selectOne(
            'SELECT a.*, CONCAT_WS(" ", u.first_name, u.last_name) AS author_name FROM announcements a LEFT JOIN users u ON u.id = a.created_by WHERE a.id = ?',
            [(int) $id]
        );
        $visible = $a !== null && (Auth::can('announcements.create')
            || ($a['status'] === 'published' && in_array($a['audience'], $this->audiences(), true)));
        if (!$visible) {
            throw new \App\Core\Exceptions\HttpException(404, 'Announcement not found.');
        }
        Database::statement('UPDATE announcements SET views = views + 1 WHERE id = ?', [$a['id']]);
        return $this->view('comms.announcements.show', ['pageTitle' => $a['title'], 'a' => $a]);
    }

    public function create(Request $request): string
    {
        $this->authorize('announcements.create');
        return $this->view('comms.announcements.form', ['pageTitle' => 'New Announcement', 'record' => [], 'isNew' => true]);
    }

    public function store(Request $request): never
    {
        $this->authorize('announcements.create');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'title'        => 'required|max:200',
            'body'         => 'required',
            'audience'     => 'required|in:all,students,staff,lecturers,admins',
            'published_at' => 'nullable|date',
            'expires_at'   => 'nullable|date',
        ]);
        $data['created_by']    = Auth::id();
        $data['published_at']  = $data['published_at'] ?? date('Y-m-d H:i:s');
        (new Announcement())->create($data);
        $this->success('Announcement published.', '/announcements');
    }

    public function edit(Request $request, string $id): string
    {
        $this->authorize('announcements.edit');
        $record = Database::selectOne('SELECT * FROM announcements WHERE id=?', [(int)$id]);
        return $this->view('comms.announcements.form', ['pageTitle' => 'Edit Announcement', 'record' => $record, 'isNew' => false]);
    }

    public function update(Request $request, string $id): never
    {
        $this->authorize('announcements.edit');
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'title'      => 'required|max:200',
            'body'       => 'required',
            'audience'   => 'required|in:all,students,staff,lecturers,admins',
            'expires_at' => 'nullable|date',
        ]);
        (new Announcement())->update((int)$id, $data);
        $this->success('Announcement updated.', '/announcements');
    }

    public function destroy(Request $request, string $id): never
    {
        $this->authorize('announcements.delete');
        $this->verifyCsrf($request);
        (new Announcement())->delete((int)$id);
        $this->success('Announcement deleted.', '/announcements');
    }
}
