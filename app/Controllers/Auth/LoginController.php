<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

final class LoginController extends Controller
{
    public function showLogin(Request $request): string
    {
        return $this->view('auth.login');
    }

    public function login(Request $request): never
    {
        $this->verifyCsrf($request);

        $data = $this->validate($request, [
            'identifier' => 'required|max:150',
            'password'   => 'required',
        ], [
            'identifier' => 'Username or email',
        ]);

        $result = Auth::attempt(
            (string) $data['identifier'],
            (string) $data['password'],
            $request->ip(),
            $request->userAgent()
        );

        if (!$result['ok']) {
            Session::keepOldInput(['identifier' => $data['identifier']]);
            $this->error($result['message'], '/login');
        }

        Csrf::rotate();
        Session::clearOldInput();

        $user = $result['user'];
        if ((int) $user['must_change_password'] === 1) {
            $this->warning('Please set a new password before continuing.', '/password/change');
        }

        $intended = Session::get('intended_url');
        Session::forget('intended_url');

        $destination = $intended && $intended !== '/login'
            ? $intended
            : $this->homeFor((string) $user['user_type']);

        $this->success('Welcome back, ' . $user['first_name'] . '.', $destination);
    }

    public function logout(Request $request): never
    {
        $this->verifyCsrf($request);
        Auth::logout();
        Session::flash('success', 'You have been signed out.');
        $this->redirect('/login');
    }

    /** Landing page appropriate to the account type. */
    private function homeFor(string $userType): string
    {
        return match ($userType) {
            'student'  => '/portal',
            'lecturer' => '/teaching',
            default    => '/dashboard',
        };
    }
}
