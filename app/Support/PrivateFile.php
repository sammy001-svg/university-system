<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use App\Core\Exceptions\HttpException;

/** Streams a stored upload to the browser once the caller has checked who may see it. */
final class PrivateFile
{
    private const INLINE = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                            'gif' => 'image/gif', 'webp' => 'image/webp', 'txt' => 'text/plain; charset=utf-8'];

    public static function send(?string $path, ?string $downloadName = null): never
    {
        $path = ltrim((string) $path, '/');
        $full = str_starts_with($path, 'private/')
            ? Config::get('paths.storage') . '/' . $path
            : Config::get('paths.public') . '/' . $path;   // files uploaded before storage became private
        if ($path === '' || str_contains($path, '..') || !is_file($full)) {
            throw new HttpException(404, 'The file is no longer available.');
        }

        $name = $downloadName !== null && $downloadName !== '' ? $downloadName : basename($full);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $type = self::INLINE[$ext] ?? 'application/octet-stream';
        $safe = (string) preg_replace('/[^A-Za-z0-9._ -]/', '_', $name);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($full));
        header('Content-Disposition: ' . (isset(self::INLINE[$ext]) ? 'inline' : 'attachment') . '; filename="' . $safe . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($full);
        exit;
    }
}
