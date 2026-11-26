<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Router;
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

        $as = $request->input('login_as') === 'student' ? 'student' : 'staff';
        Session::keepOldInput(['login_as' => $as, 'identifier' => (string) $request->input('identifier', '')]);

        $data = $this->validate($request, [
            'identifier' => 'required|max:150',
            'password'   => 'required',
        ], [
            'identifier' => $as === 'student' ? 'Student ID number' : 'Username or email',
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

        // The page somebody was heading for before signing in. It is only safe to
        // replay when this account may actually open it: the browser may have been
        // bounced from a student's portal page and then used by a member of staff,
        // and following it blindly lands them on a 403 instead of their own home.
        $intended = Session::get('intended_url');
        Session::forget('intended_url');

        $home   = $this->homeFor((string) $user['user_type']);
        $router = Router::current();
        $usable = $intended !== null && $intended !== ''
            && !str_contains((string) $intended, '/login')
            && $this->ownWorkspace((string) $intended, (string) $user['user_type'])
            && ($router === null || $router->allows((string) $intended));

        $destination = $usable ? (string) $intended : $home;

        $this->success('Welcome back, ' . $user['first_name'] . '.', $destination);
    }

    public function logout(Request $request): never
    {
        $this->verifyCsrf($request);
        Auth::logout();
        Session::flash('success', 'You have been signed out.');
        $this->redirect('/login');
    }

    /**
     * The student portal and the family pages belong to one person's own record,
     * so there is nothing there for anybody else - a Super Administrator holds
     * the permission but has no student record, and would land on a dead end.
     */
    private function ownWorkspace(string $path, string $userType): bool
    {
        foreach (['/portal' => 'student', '/family' => 'parent'] as $prefix => $owner) {
            if (str_starts_with($path, $prefix) && $userType !== $owner) {
                return false;
            }
        }
        return true;
    }

    /**
     * Each account type's own workspace, with the permission that page demands.
     * /family and /dashboard ask for nothing beyond being signed in.
     */
    private const HOMES = [
        'student'  => ['/portal',   'portal.access'],
        'lecturer' => ['/teaching', 'teaching.access'],
        'parent'   => ['/family',   null],
    ];

    /**
     * Where to land this account. Somebody whose own workspace would refuse them
     * is missing a role they should hold; sending them there anyway produces a
     * bare 403 at sign-in, and /dashboard is no refuge because it bounces them
     * straight back. They go to their profile, which asks nothing of anybody, and
     * the gap is logged so an administrator can put the role back.
     */
    private function homeFor(string $userType): string
    {
        [$path, $permission] = self::HOMES[$userType] ?? ['/dashboard', null];
        if ($permission === null || Auth::can($permission)) {
            return $path;
        }

        Logger::warning(sprintf(
            'Account cannot open its own workspace: %s (user #%d, type %s) needs %s for %s',
            Auth::user()['username'] ?? '?', (int) Auth::id(), $userType, $permission, $path
        ));
        return '/profile';
    }
}
