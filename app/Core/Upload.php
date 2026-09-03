<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Validated file uploads with extension/MIME whitelisting.
 */
final class Upload
{
    public const IMAGES    = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    public const DOCUMENTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx'];

    private const MIME_MAP = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'csv'  => ['text/csv', 'text/plain', 'application/csv'],
        'txt'  => ['text/plain'],
    ];

    /**
     * @return array{path:string,name:string,size:int,mime:string} relative path from /public
     */
    public static function store(array $file, string $folder, array $allowed = self::IMAGES, int $maxMb = 5): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::errorMessage((int) $file['error']));
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid upload.');
        }
        if ($file['size'] > $maxMb * 1024 * 1024) {
            throw new RuntimeException("File is larger than the {$maxMb} MB limit.");
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed, true)) {
            throw new RuntimeException('File type not allowed. Permitted: ' . implode(', ', $allowed) . '.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($file['tmp_name']);
        $valid = self::MIME_MAP[$extension] ?? [];
        if ($valid !== [] && !in_array($mime, $valid, true)) {
            throw new RuntimeException('The file contents do not match its extension.');
        }

        $folder    = trim($folder, '/');
        $directory = Config::get('paths.uploads') . '/' . $folder;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the upload directory.');
        }

        $safeName = bin2hex(random_bytes(8)) . '-' . time() . '.' . $extension;
        $target   = $directory . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Could not save the uploaded file.');
        }
        @chmod($target, 0644);

        return [
            'path' => 'uploads/' . $folder . '/' . $safeName,
            'name' => basename((string) $file['name']),
            'size' => (int) $file['size'],
            'mime' => $mime,
        ];
    }

    public static function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $full = Config::get('paths.public') . '/' . ltrim($relativePath, '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    private static function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file is too large.',
            UPLOAD_ERR_PARTIAL                        => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE                        => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Server is missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE                     => 'Failed to write the file to disk.',
            default                                   => 'The upload failed.',
        };
    }
}
