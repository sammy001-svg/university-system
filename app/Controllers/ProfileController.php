<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;

final class ProfileController extends Controller
{
    public function show(Request $request): string
    {
        return $this->view('profile.show', [
            'pageTitle' => 'My Profile',
            'user'      => Auth::user(),
        ]);
    }

    public function update(Request $request): never
    {
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'first_name' => 'required|max:80',
            'last_name'  => 'required|max:80',
            'other_name' => 'nullable|max:80',
            'phone'      => 'nullable|max:30',
            'alt_phone'  => 'nullable|max:30',
        ]);

        Database::statement(
            'UPDATE users SET first_name=?, last_name=?, other_name=?, phone=?, alt_phone=? WHERE id=?',
            [$data['first_name'], $data['last_name'], $data['other_name'] ?? null,
             $data['phone'] ?? null, $data['alt_phone'] ?? null, Auth::id()]
        );

        $this->success('Profile updated successfully.', '/profile');
    }
}
