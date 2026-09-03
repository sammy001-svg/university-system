<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . self::url($path), true, $status);
        exit;
    }

    public static function back(string $fallback = '/'): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '' && str_starts_with($referer, Config::get('app.url', ''))) {
            header('Location: ' . $referer, true, 302);
            exit;
        }
        self::redirect($fallback);
    }

    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function text(string $body, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    public static function download(string $filePath, ?string $downloadName = null): never
    {
        if (!is_file($filePath)) {
            http_response_code(404);
            exit('File not found');
        }
        $name = $downloadName ?? basename($filePath);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        readfile($filePath);
        exit;
    }

    /** Stream an array of rows to the browser as CSV. */
    public static function csv(array $rows, string $filename, array $headings = []): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, chr(239) . chr(187) . chr(191)); // UTF-8 BOM for Excel
        if ($headings !== []) {
            fputcsv($out, $headings);
        } elseif ($rows !== []) {
            fputcsv($out, array_keys((array) $rows[0]));
        }
        foreach ($rows as $row) {
            fputcsv($out, array_values((array) $row));
        }
        fclose($out);
        exit;
    }

    /** Build an absolute URL from an application path. */
    public static function url(string $path = '/'): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $base = rtrim((string) Config::get('app.url', ''), '/');
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dir    = str_replace(DIRECTORY_SEPARATOR, '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
            $base   = $scheme . '://' . $host . ($dir === '/' ? '' : $dir);
        }
        return $base . '/' . ltrim($path, '/');
    }
}
