<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\Security\TwoFactor;

final class LoginController extends Controller
{
    public function showLogin(Request $request): string
    {
        return $this->view('auth.login');
    }

    public function login(Request $request): never
    {
        $this->verifyCsrf($request);

        $as = in_array($request->input('login_as'), ['student', 'parent'], true) ? (string) $request->input('login_as') : 'staff';
        Session::keepOldInput(['login_as' => $as, 'identifier' => (string) $request->input('identifier', '')]);

        $data = $this->validate($request, [
            'identifier' => 'required|max:150',
            'password'   => 'required',
        ], [
            'identifier' => ['student' => 'Admission number', 'parent' => 'Username, email or phone'][$as] ?? 'Username or email',
        ]);

        $result = Auth::attempt(
            (string) $data['identifier'],
            (string) $data['password'],
            $request->ip(),
            $request->userAgent(),
            $as
        );

        if (!$result['ok']) {
            $this->error($result['message'], '/login' . ($as !== 'staff' ? '?as=' . $as : ''));
        }
        if (!empty($result['two_factor'])) {
            $this->redirect('/login/two-factor');
        }
        $this->finishLogin($result['user']);
    }

    /** Second step for accounts with an authenticator app. */
    public function twoFactorForm(Request $request): string
    {
        if (Auth::pendingTwoFactor() === null) {
            $this->redirect('/login');
        }
        return $this->view('auth.two-factor', ['pageTitle' => 'Two-step sign-in']);
    }

    public function twoFactor(Request $request): never
    {
        $this->verifyCsrf($request);
        $pending = Auth::pendingTwoFactor();
        if ($pending === null) {
            $this->error('Your sign-in timed out. Please enter your password again.', '/login');
        }
        $code = trim((string) $request->input('code', ''));
        $ok   = strlen((string) preg_replace('/\D/', '', $code)) === 6 && !str_contains($code, '-')
            ? TwoFactor::verify((string) $pending['two_factor_secret'], $code)
            : TwoFactor::useRecoveryCode($pending, $code);
        if (!$ok) {
            Audit::log('2fa_failed', 'auth', 'users', (string) $pending['id'], 'Wrong two-step sign-in code');
            $fails = (int) Session::get('auth_2fa_fails', 0) + 1;
            Session::put('auth_2fa_fails', $fails);
            if ($fails >= 5) {
                Session::forget('auth_2fa_pending');
                Session::forget('auth_2fa_fails');
                $this->error('Too many wrong codes. Please sign in again.', '/login');
            }
            $this->error('That code is not right. Check the time on your phone and try the newest code.', '/login/two-factor');
        }
        Session::forget('auth_2fa_fails');
        Auth::completeTwoFactor($pending, $request->ip());
        $this->finishLogin($pending);
    }

    private function finishLogin(array $user): never
    {
        Csrf::rotate();
        Session::clearOldInput();

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
            'parent'   => '/family',
            'lecturer' => '/teaching',
            default    => '/dashboard',
        };
    }
}
