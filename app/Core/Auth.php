<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session-backed authentication with lockout and audit trail.
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user_id';
    private static ?array $user = null;

    public static function attempt(string $identifier, string $password, string $ip, string $agent): array
    {
        $user = Database::selectOne(
            'SELECT * FROM users
              WHERE (username = ? OR email = ?) AND deleted_at IS NULL
              LIMIT 1',
            [$identifier, $identifier]
        );

        self::logAttempt($identifier, $ip, $agent, false);

        if ($user === null) {
            return ['ok' => false, 'message' => 'Invalid credentials. Please try again.'];
        }

        if ($user['locked_until'] !== null && strtotime($user['locked_until']) > time()) {
            $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['ok' => false, 'message' => "Account temporarily locked. Try again in {$minutes} minute(s)."];
        }

        if (!Hash::check($password, $user['password_hash'])) {
            self::registerFailure($user);
            return ['ok' => false, 'message' => 'Invalid credentials. Please try again.'];
        }

        if ($user['status'] === 'suspended') {
            return ['ok' => false, 'message' => 'Your account has been suspended. Contact the administrator.'];
        }
        if ($user['status'] === 'inactive') {
            return ['ok' => false, 'message' => 'Your account is inactive. Contact the administrator.'];
        }
        if ($user['status'] === 'pending') {
            return ['ok' => false, 'message' => 'Your account is awaiting activation.'];
        }

        // Upgrade the hash silently if the cost factor changed.
        if (Hash::needsRehash($user['password_hash'])) {
            Database::statement('UPDATE users SET password_hash = ? WHERE id = ?', [Hash::make($password), $user['id']]);
        }

        self::login($user, $ip);
        self::logAttempt($identifier, $ip, $agent, true);

        return ['ok' => true, 'user' => $user];
    }

    private static function registerFailure(array $user): void
    {
        $max      = (int) Config::get('security.max_login_attempts', 5);
        $attempts = (int) $user['failed_attempts'] + 1;

        if ($attempts >= $max) {
            $lockUntil = date('Y-m-d H:i:s', time() + ((int) Config::get('security.lockout_minutes', 15) * 60));
            Database::statement(
                'UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?',
                [$attempts, $lockUntil, $user['id']]
            );
        } else {
            Database::statement('UPDATE users SET failed_attempts = ? WHERE id = ?', [$attempts, $user['id']]);
        }
    }

    private static function logAttempt(string $identifier, string $ip, string $agent, bool $ok): void
    {
        Database::statement(
            'INSERT INTO login_attempts (identifier, ip_address, user_agent, successful) VALUES (?, ?, ?, ?)',
            [$identifier, $ip, $agent, $ok ? 1 : 0]
        );
    }

    public static function login(array $user, string $ip = ''): void
    {
        Session::regenerate();
        Session::put(self::SESSION_KEY, (int) $user['id']);
        Session::put('auth_user_type', $user['user_type']);
        self::$user = $user;

        Database::statement(
            'UPDATE users SET last_login_at = NOW(), last_login_ip = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?',
            [$ip, $user['id']]
        );
        Acl::flush((int) $user['id']);
        Audit::log('login', 'auth', 'users', (string) $user['id'], 'User signed in');
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('logout', 'auth', 'users', (string) self::id(), 'User signed out');
        }
        self::$user = null;
        Session::destroy();
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);
        return $id === null ? null : (int) $id;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $id = self::id();
        if ($id === null) {
            return null;
        }
        $user = Database::selectOne('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$id]);
        if ($user === null) {
            Session::destroy();
            return null;
        }
        unset($user['password_hash'], $user['remember_token']);
        return self::$user = $user;
    }

    public static function fullName(): string
    {
        $user = self::user();
        if ($user === null) {
            return 'Guest';
        }
        return trim(($user['title'] ? $user['title'] . ' ' : '') . $user['first_name'] . ' ' . $user['last_name']);
    }

    public static function type(): string
    {
        return (string) (self::user()['user_type'] ?? 'guest');
    }

    public static function can(string|array $permission, bool $requireAll = false): bool
    {
        $id = self::id();
        return $id !== null && Acl::can($id, $permission, $requireAll);
    }

    public static function hasRole(string|array $roles): bool
    {
        $id = self::id();
        return $id !== null && Acl::hasRole($id, $roles);
    }

    public static function isSuperAdmin(): bool
    {
        $id = self::id();
        return $id !== null && Acl::isSuperAdmin($id);
    }

    /** The students row for the signed-in student, if any. */
    public static function student(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        return Database::selectOne('SELECT * FROM students WHERE user_id = ?', [$id]);
    }

    /** The staff row for the signed-in employee, if any. */
    public static function staff(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        return Database::selectOne('SELECT * FROM staff WHERE user_id = ?', [$id]);
    }

    public static function refresh(): void
    {
        self::$user = null;
    }
}
