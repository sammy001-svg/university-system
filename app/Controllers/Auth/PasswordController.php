<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Hash;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

final class PasswordController extends Controller
{
    private const TOKEN_TTL_MINUTES = 60;

    public function showForgot(Request $request): string
    {
        return $this->view('auth.forgot');
    }

    public function sendResetLink(Request $request): never
    {
        $this->verifyCsrf($request);
        $data  = $this->validate($request, ['email' => 'required|email|max:150']);
        $email = strtolower((string) $data['email']);

        $user = Database::selectOne(
            'SELECT id, first_name, email FROM users WHERE email = ? AND deleted_at IS NULL',
            [$email]
        );

        // Always answer the same way so the form cannot enumerate accounts.
        if ($user !== null) {
            $token = Hash::random(32);
            Database::statement(
                'INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)',
                [$email, hash('sha256', $token), date('Y-m-d H:i:s', time() + (self::TOKEN_TTL_MINUTES * 60))]
            );

            $link = Response::url('/password/reset?token=' . $token . '&email=' . urlencode($email));
            $body = '<p>Hello ' . e($user['first_name']) . ',</p>'
                  . '<p>A password reset was requested for your account. The link below is valid for '
                  . self::TOKEN_TTL_MINUTES . ' minutes.</p>'
                  . '<p><a href="' . $link . '" style="background:#1d4ed8;color:#fff;padding:10px 18px;'
                  . 'border-radius:8px;display:inline-block;text-decoration:none">Reset my password</a></p>'
                  . '<p style="font-size:12px;color:#64748b">If you did not request this, no action is needed.</p>';

            Mailer::send($email, 'Reset your password', $body);
            Audit::log('password_reset_requested', 'auth', 'users', (string) $user['id']);
        }

        $this->success('If that email is registered, a reset link is on its way.', '/login');
    }

    public function showReset(Request $request): string
    {
        return $this->view('auth.reset', [
            'token' => (string) $request->query('token', ''),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): never
    {
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|password|confirmed',
        ]);

        $record = Database::selectOne(
            'SELECT * FROM password_resets
              WHERE email = ? AND token = ? AND used_at IS NULL AND expires_at > NOW()
              ORDER BY id DESC LIMIT 1',
            [strtolower((string) $data['email']), hash('sha256', (string) $data['token'])]
        );

        if ($record === null) {
            $this->error('That reset link is invalid or has expired.', '/password/forgot');
        }

        $user = Database::selectOne('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL', [$data['email']]);
        if ($user === null) {
            $this->error('No account matches that email address.', '/password/forgot');
        }

        (new User())->setPassword((int) $user['id'], (string) $data['password'], false);
        Database::statement('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$record['id']]);
        Audit::log('password_reset', 'auth', 'users', (string) $user['id'], 'Password reset via email link');

        $this->success('Your password has been updated. You can now sign in.', '/login');
    }

    public function showChange(Request $request): string
    {
        return $this->view('auth.change-password');
    }

    public function change(Request $request): never
    {
        $this->verifyCsrf($request);
        $data = $this->validate($request, [
            'current_password' => 'required',
            'password'         => 'required|password|confirmed',
        ]);

        $userId = Auth::id();
        $stored = (string) Database::scalar('SELECT password_hash FROM users WHERE id = ?', [$userId]);

        if (!Hash::check((string) $data['current_password'], $stored)) {
            $this->error('Your current password is not correct.', '/password/change');
        }
        if (Hash::check((string) $data['password'], $stored)) {
            $this->error('Please choose a password you have not used before.', '/password/change');
        }

        (new User())->setPassword((int) $userId, (string) $data['password'], false);
        Auth::refresh();
        Audit::log('password_changed', 'auth', 'users', (string) $userId, 'Password changed by user');

        $this->success('Your password has been updated.', '/dashboard');
    }
}
