<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP template renderer with layout inheritance and sections.
 */
final class View
{
    private static array $shared = [];
    private static array $sections = [];
    private static array $sectionStack = [];
    private static ?string $layout = null;

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function shared(): array
    {
        return self::$shared;
    }

    /** Render a view and return the resulting HTML. */
    public static function render(string $view, array $data = []): string
    {
        $previousLayout   = self::$layout;
        self::$layout     = null;

        $content = self::capture($view, $data);

        if (self::$layout !== null) {
            $layout       = self::$layout;
            self::$layout = null;
            self::$sections['content'] = self::$sections['content'] ?? $content;
            $content = self::capture($layout, $data);
        }

        self::$layout = $previousLayout;
        return $content;
    }

    public static function display(string $view, array $data = []): void
    {
        echo self::render($view, $data);
    }

    private static function capture(string $view, array $data): string
    {
        $path = self::path($view);
        if (!is_file($path)) {
            throw new RuntimeException("View not found: {$view} ({$path})");
        }
        extract(self::$shared, EXTR_SKIP);
        extract($data, EXTR_OVERWRITE);

        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    public static function path(string $view): string
    {
        $view = str_replace('.', '/', trim($view, '/'));
        return Config::get('paths.views') . '/' . $view . '.php';
    }

    public static function exists(string $view): bool
    {
        return is_file(self::path($view));
    }

    /** Called from inside a view to declare its layout. */
    public static function extend(string $layout): void
    {
        self::$layout = $layout;
    }

    public static function startSection(string $name): void
    {
        self::$sectionStack[] = $name;
        ob_start();
    }

    public static function endSection(): void
    {
        $name = array_pop(self::$sectionStack);
        if ($name === null) {
            throw new RuntimeException('endSection() called without a matching startSection().');
        }
        self::$sections[$name] = (string) ob_get_clean();
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function hasSection(string $name): bool
    {
        return isset(self::$sections[$name]) && trim(self::$sections[$name]) !== '';
    }

    public static function setSection(string $name, string $value): void
    {
        self::$sections[$name] = $value;
    }

    public static function reset(): void
    {
        self::$sections     = [];
        self::$sectionStack = [];
        self::$layout       = null;
    }

    /** Include a partial view inside another view. */
    public static function partial(string $view, array $data = []): void
    {
        echo self::capture($view, $data);
    }
}
