<?php
declare(strict_types=1);

namespace App\Core;

final class Logger
{
    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = Config::get('paths.storage', dirname(__DIR__, 2) . '/storage') . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s%s%s",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' ' . json_encode($context),
            PHP_EOL
        );
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function exception(\Throwable $e): void
    {
        self::write('error', get_class($e) . ': ' . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString()),
        ]);
    }
}
