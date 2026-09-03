<?php
/**
 * Application configuration.
 * Values come from the .env file; the arrays below are the defaults.
 */

declare(strict_types=1);

/** Minimal .env parser (no external dependency). */
function ums_load_env(string $path): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    if (!is_file($path)) {
        return $cache;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (strlen($value) > 1 && (
            ($value[0] === '"' && str_ends_with($value, '"')) ||
            ($value[0] === "'" && str_ends_with($value, "'"))
        )) {
            $value = substr($value, 1, -1);
        }
        $cache[$key] = $value;
    }
    return $cache;
}

function env(string $key, mixed $default = null): mixed
{
    $vars = ums_load_env(dirname(__DIR__) . '/.env');
    if (!array_key_exists($key, $vars)) {
        return $default;
    }
    $value = $vars[$key];
    return match (strtolower((string) $value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        'empty', '(empty)' => '',
        default            => $value,
    };
}

return [
    'app' => [
        'name'       => env('APP_NAME', 'Best Brain University'),
        'short_name' => env('APP_SHORT_NAME', 'BBU'),
        'env'        => env('APP_ENV', 'production'),
        'debug'      => (bool) env('APP_DEBUG', false),
        'url'        => rtrim((string) env('APP_URL', ''), '/'),
        'timezone'   => env('APP_TIMEZONE', 'Africa/Monrovia'),
        'key'        => env('APP_KEY', 'insecure-development-key'),
        'version'    => '1.0.0',
    ],
    'database' => [
        'host'    => env('DB_HOST', '127.0.0.1'),
        'port'    => (int) env('DB_PORT', 3306),
        'name'    => env('DB_NAME', 'university_db'),
        'user'    => env('DB_USER', 'root'),
        'pass'    => env('DB_PASS', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    'session' => [
        'name'     => env('SESSION_NAME', 'ums_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'secure'   => (bool) env('SESSION_SECURE', false),
    ],
    'mail' => [
        'enabled'   => (bool) env('MAIL_ENABLED', false),
        'host'      => env('MAIL_HOST', 'localhost'),
        'port'      => (int) env('MAIL_PORT', 25),
        'user'      => env('MAIL_USER', ''),
        'pass'      => env('MAIL_PASS', ''),
        'from'      => env('MAIL_FROM', 'no-reply@localhost'),
        'from_name' => env('MAIL_FROM_NAME', 'Best Brain University'),
    ],
    'security' => [
        'max_login_attempts'  => (int) env('MAX_LOGIN_ATTEMPTS', 5),
        'lockout_minutes'     => (int) env('LOCKOUT_MINUTES', 15),
        'password_min_length' => (int) env('PASSWORD_MIN_LENGTH', 8),
    ],
    'locale' => [
        'currency'        => env('CURRENCY', 'USD'),
        'currency_symbol' => env('CURRENCY_SYMBOL', '$'),
        'date_format'     => 'd M Y',
        'datetime_format' => 'd M Y H:i',
    ],
    'paths' => [
        'base'    => dirname(__DIR__),
        'app'     => dirname(__DIR__) . '/app',
        'views'   => dirname(__DIR__) . '/app/Views',
        'storage' => dirname(__DIR__) . '/storage',
        'public'  => dirname(__DIR__) . '/public',
        'uploads' => dirname(__DIR__) . '/public/uploads',
    ],
];
