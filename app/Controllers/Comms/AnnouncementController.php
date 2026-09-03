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
    public function index(Request $request): string
    {
        $this->authorize('announcements.view');
        $announcements = Database::select(
            'SELECT a.*, u.first_name, u.last_name FROM announcements a JOIN users u ON u.id=a.created_by ORDER BY a.published_at DESC LIMIT 100'
        );
        return $this->view('comms.announcements.index', ['pageTitle' => 'Announcements', 'announcements' => $announcements]);
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
