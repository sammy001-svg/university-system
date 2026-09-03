<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $query;
    private array $body;
    private array $files;
    private array $server;
    private array $attributes = [];

    public function __construct()
    {
        $this->query  = $_GET;
        $this->body   = $_POST;
        $this->files  = $_FILES;
        $this->server = $_SERVER;

        // Accept JSON payloads for the AJAX endpoints.
        if (str_contains($this->header('Content-Type', ''), 'application/json')) {
            $raw = file_get_contents('php://input');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $this->body = array_merge($this->body, $decoded);
                }
            }
        }
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        // Browsers only send GET/POST; honour the _method override field.
        if ($method === 'POST' && isset($this->body['_method'])) {
            $override = strtoupper((string) $this->body['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $method;
    }

    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Strip the sub-directory the app is installed under.
        $script = str_replace(DIRECTORY_SEPARATOR, '/', dirname((string) ($this->server['SCRIPT_NAME'] ?? '')));
        if ($script !== '/' && $script !== '' && str_starts_with($uri, $script)) {
            $uri = substr($uri, strlen($script));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function raw(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key): bool
    {
        $value = $this->input($key);
        return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true);
    }

    public function array(string $key): array
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    /** @return array<string,mixed> only the requested keys that are present */
    public function only(array $keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $out[$key] = is_string($all[$key]) ? trim($all[$key]) : $all[$key];
            }
        }
        return $out;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->body) || array_key_exists($key, $this->query);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '' && $value !== [];
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalised = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$normalised] ?? $this->server[strtoupper(str_replace('-', '_', $key))] ?? $default;
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                $ip = explode(',', (string) $this->server[$key])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_contains((string) $this->header('Accept', ''), 'application/json');
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
