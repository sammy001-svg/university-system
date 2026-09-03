<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Database-backed application settings with a per-request cache.
 */
final class Setting
{
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        self::$cache = [];
        try {
            foreach (Database::select('SELECT setting_key, setting_value, data_type FROM settings') as $row) {
                self::$cache[$row['setting_key']] = self::cast($row['setting_value'], $row['data_type']);
            }
        } catch (\Throwable $e) {
            Logger::warning('Settings table unavailable: ' . $e->getMessage());
        }
        return self::$cache;
    }

    private static function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'integer' => (int) $value,
            'decimal' => (float) $value,
            'boolean' => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = self::load();
        return $settings[$key] ?? $default;
    }

    public static function set(string $key, mixed $value, string $type = 'string', string $group = 'general'): void
    {
        $stored = is_array($value) ? json_encode($value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
        Database::statement(
            'INSERT INTO settings (setting_key, setting_value, data_type, setting_group)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $stored, $type, $group]
        );
        self::$cache = null;
    }

    public static function all(?string $group = null): array
    {
        if ($group === null) {
            return self::load();
        }
        $rows = Database::select('SELECT * FROM settings WHERE setting_group = ? ORDER BY id', [$group]);
        return $rows;
    }

    public static function groups(): array
    {
        return array_column(
            Database::select('SELECT DISTINCT setting_group FROM settings ORDER BY setting_group'),
            'setting_group'
        );
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
