<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Hash;
use App\Core\Request;
use App\Core\Session;
use App\Core\Setting;
use App\Services\Security\TwoFactor;

/** "Account security": two-step sign-in and the devices signed in to the account. */
final class SecurityController extends Controller
{
    public function index(Request $request): string
    {
        $user = Database::selectOne('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        $pendingSecret = Session::get('2fa_setup_secret');
        return $this->view('security.index', [
            'pageTitle'     => 'Account security',
            'user'          => $user,
            'enabled'       => !empty($user['two_factor_enabled_at']),
            'setupSecret'   => is_string($pendingSecret) ? $pendingSecret : null,
            'setupUri'      => is_string($pendingSecret) ? TwoFactor::uri($pendingSecret, (string) $user['username'], (string) Setting::get('institution_short_name', 'UMS')) : null,
            'recoveryLeft'  => TwoFactor::remainingRecoveryCodes($user),
            'newCodes'      => Session::getFlash('recovery_codes'),
            'required'      => self::twoFactorRequired($user),
            'sessions'      => Database::select(
                'SELECT * FROM user_sessions WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_seen_at DESC',
                [Auth::id()]
            ),
            'currentHash'   => (string) Session::get('auth_session_hash', ''),
        ]);
    }

    public function startTwoFactor(Request $request): never
    {
        $this->verifyCsrf($request);
        Session::put('2fa_setup_secret', TwoFactor::newSecret());
        $this->redirect('/account/security#two-factor');
    }

    public function enableTwoFactor(Request $request): never
    {
        $this->verifyCsrf($request);
        $secret = Session::get('2fa_setup_secret');
        if (!is_string($secret) || !TwoFactor::verify($secret, (string) $request->input('code', ''))) {
            $this->error('That code did not match. Make sure you scanned the new QR code, then enter the current 6-digit code.', '/account/security#two-factor');
        }
        Database::statement('UPDATE users SET two_factor_secret = ?, two_factor_enabled_at = ? WHERE id = ?', [$secret, date('Y-m-d H:i:s'), Auth::id()]);
        Session::forget('2fa_setup_secret');
        Session::flash('recovery_codes', TwoFactor::newRecoveryCodes((int) Auth::id()));
        Audit::log('2fa_enabled', 'auth', 'users', (string) Auth::id(), 'Two-step sign-in turned on');
        $this->success('Two-step sign-in is on. Save your recovery codes now.', '/account/security#two-factor');
    }

    public function disableTwoFactor(Request $request): never
    {
        $this->verifyCsrf($request);
        $user = Database::selectOne('SELECT * FROM users WHERE id = ?', [Auth::id()]);
        if (!Hash::check((string) $request->input('password', ''), (string) $user['password_hash'])) {
            $this->error('Your password was not right.', '/account/security#two-factor');
        }
        if (self::twoFactorRequired($user)) {
            $this->error('Two-step sign-in is required for your account and cannot be turned off.', '/account/security#two-factor');
        }
        Database::statement('UPDATE users SET two_factor_secret = NULL, two_factor_enabled_at = NULL, two_factor_recovery_codes = NULL WHERE id = ?', [Auth::id()]);
        Audit::log('2fa_disabled', 'auth', 'users', (string) Auth::id(), 'Two-step sign-in turned off');
        $this->success('Two-step sign-in is off.', '/account/security#two-factor');
    }

    public function newRecoveryCodes(Request $request): never
    {
        $this->verifyCsrf($request);
        Session::flash('recovery_codes', TwoFactor::newRecoveryCodes((int) Auth::id()));
        $this->success('New recovery codes created; the old ones no longer work.', '/account/security#two-factor');
    }

    /** Sign out one of my devices, or all the others. */
    public function revokeSession(Request $request, string $id): never
    {
        $this->verifyCsrf($request);
        $current = (string) Session::get('auth_session_hash', '');
        if ($id === 'others') {
            Database::statement('UPDATE user_sessions SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL AND session_hash <> ?',
                [date('Y-m-d H:i:s'), Auth::id(), $current]);
            $this->success('All your other devices have been signed out.', '/account/security#sessions');
        }
        Database::statement('UPDATE user_sessions SET revoked_at = ? WHERE id = ? AND user_id = ? AND session_hash <> ?',
            [date('Y-m-d H:i:s'), (int) $id, Auth::id(), $current]);
        $this->success('That device has been signed out.', '/account/security#sessions');
    }

    /** Administrators must use two-step sign-in when Settings > Security says so. */
    public static function twoFactorRequired(array $user): bool
    {
        return (bool) Setting::get('require_2fa_admins', false) && $user['user_type'] === 'admin';
    }
}
