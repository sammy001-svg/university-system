<?php
declare(strict_types=1);

namespace App\Services\DataImport;

use App\Core\Database;
use App\Core\Logger;
use App\Services\AcademicCatalogSync;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Imports the data files in database/data/ (students, lecturers, staff)
 * automatically, the way AcademicCatalogSync keeps the catalogue current.
 *
 * syncIfChanged() runs on every request after the catalogue sync. While no
 * file has changed it only compares a fingerprint with a cache file. When a
 * file is new or edited, the next request imports it - files run in name
 * order (01_, 02_ ...) - and the result is recorded in `data_imports`, where
 * Admin > Data Imports shows it. Each file is imported once per version; one
 * that failed is retried when the importer code or the catalogue changes.
 */
final class DataImportSync
{
    public const IMPORTERS = [
        'billing_forms' => BillingFormImporter::class,
        'student_list'  => StudentListImporter::class,
        'lecturers'     => LecturerImporter::class,
        'staff'         => StaffImporter::class,
    ];
    private const LOCK_NAME     = 'ums_data_import_sync';
    private const RETRY_SECONDS = 600;

    public function __construct(private string $basePath)
    {
    }

    /** @return list<string> data files, in import order */
    public function files(): array
    {
        $files = glob($this->basePath . '/database/data/*.php') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    /** Version of the code that does the importing (importers + catalogue); a failed file is retried when it changes. */
    public function codeVersion(): string
    {
        $parts = [(new AcademicCatalogSync($this->basePath))->fingerprint()];
        foreach (glob(__DIR__ . '/*.php') ?: [] as $file) {
            $parts[] = basename($file) . (string) sha1_file($file);
        }
        $parts[] = (string) sha1_file($this->basePath . '/app/Services/BillingService.php');
        return sha1(implode('|', $parts));
    }

    public function fingerprint(): string
    {
        $parts = [$this->codeVersion()];
        foreach ($this->files() as $file) {
            $parts[] = basename($file) . (string) sha1_file($file);
        }
        return sha1(implode('|', $parts));
    }

    /**
     * Called once per request; never throws, so a failed import cannot take the site down.
     * $connect supplies the PDO connection and is only called when an import may be due.
     */
    public function syncIfChanged(callable $connect): void
    {
        try {
            $fingerprint = $this->fingerprint();
            $cached      = $this->readCache();
            if ($cached === 'ok:' . $fingerprint) {
                return;
            }
            if (preg_match('/^failed:' . $fingerprint . ':(\d+)$/', $cached, $m) && time() - (int) $m[1] < self::RETRY_SECONDS) {
                return;
            }
            /** @var PDO $pdo */
            $pdo = $connect();
            // One request imports; the others carry on with the current data.
            if ((int) $pdo->query("SELECT GET_LOCK('" . self::LOCK_NAME . "', 0)")->fetchColumn() !== 1) {
                return;
            }
            try {
                @set_time_limit(0);
                ignore_user_abort(true);
                $this->importPending();
                $this->writeCache('ok:' . $fingerprint);
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
            }
        } catch (Throwable $e) {
            Logger::error('Data import failed: ' . $e->getMessage());
            $this->writeCache('failed:' . ($fingerprint ?? '') . ':' . time());
        }
    }

    /**
     * Import every file that is new, changed, or failed under older code.
     * @return array<string,ImportReport> file name => report, for the files imported now
     */
    public function importPending(bool $force = false): array
    {
        self::ensureTable();
        $code = $this->codeVersion();
        $done = [];
        foreach (Database::select('SELECT file_name, checksum, code_version, status FROM data_imports') as $row) {
            $done[$row['file_name']] = $row;
        }
        $reports = [];
        foreach ($this->files() as $file) {
            $name     = basename($file);
            $checksum = (string) sha1_file($file);
            $previous = $done[$name] ?? null;
            $current  = $previous !== null && $previous['checksum'] === $checksum
                && ($previous['status'] === 'ok' || $previous['code_version'] === $code);
            if ($current && !$force) {
                continue;
            }
            $reports[$name] = $this->importFile($file, $checksum, $code);
        }
        return $reports;
    }

    /** Import one data file now and record the outcome. */
    public function importFile(string $file, ?string $checksum = null, ?string $code = null, bool $dryRun = false): ImportReport
    {
        $report  = new ImportReport();
        $started = microtime(true);
        $type    = null;
        try {
            $data = (static fn (string $path): mixed => require $path)($file);
            $type = is_array($data) ? ($data['type'] ?? null) : null;
            if (!isset(self::IMPORTERS[$type])) {
                throw new RuntimeException('The file has no recognised "type" (expected one of: ' . implode(', ', array_keys(self::IMPORTERS)) . ').');
            }
            $class = self::IMPORTERS[$type];
            (new $class())->run($data, $report, $dryRun);
            $status = $report->failed > 0 ? 'partial' : 'ok';
        } catch (Throwable $e) {
            while (Database::connection()->inTransaction()) {
                Database::connection()->rollBack();
            }
            $report->line('STOPPED: ' . $e->getMessage());
            $status = 'failed';
        }
        if ($dryRun) {
            return $report;
        }

        $name = basename($file);
        Database::statement(
            'INSERT INTO data_imports (file_name, import_type, source, checksum, code_version, status,
                                       created_count, updated_count, failed_count, report, duration_ms, imported_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE import_type = VALUES(import_type), source = VALUES(source), checksum = VALUES(checksum),
                 code_version = VALUES(code_version), status = VALUES(status), created_count = VALUES(created_count),
                 updated_count = VALUES(updated_count), failed_count = VALUES(failed_count), report = VALUES(report),
                 duration_ms = VALUES(duration_ms), imported_at = VALUES(imported_at)',
            [$name, $type, isset($data['source']) ? mb_substr((string) $data['source'], 0, 255) : null,
             $checksum ?? (string) sha1_file($file), $code ?? $this->codeVersion(), $status,
             $report->created, $report->updated, $report->failed, $report->text(), (int) ((microtime(true) - $started) * 1000)]
        );
        Logger::write($status === 'ok' ? 'info' : 'error', "Data import {$name}: {$status}, " . $report->totals());
        return $report;
    }

    /** The results table; created here as well as in schema.sql so an older database picks it up. */
    public static function ensureTable(): void
    {
        Database::statement(
            "CREATE TABLE IF NOT EXISTS `data_imports` (
              `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `file_name`     VARCHAR(150) NOT NULL,
              `import_type`   VARCHAR(40)           DEFAULT NULL,
              `source`        VARCHAR(255)          DEFAULT NULL,
              `checksum`      CHAR(40)     NOT NULL,
              `code_version`  CHAR(40)     NOT NULL,
              `status`        ENUM('ok','partial','failed') NOT NULL,
              `created_count` INT UNSIGNED NOT NULL DEFAULT 0,
              `updated_count` INT UNSIGNED NOT NULL DEFAULT 0,
              `failed_count`  INT UNSIGNED NOT NULL DEFAULT 0,
              `report`        MEDIUMTEXT            DEFAULT NULL,
              `duration_ms`   INT UNSIGNED NOT NULL DEFAULT 0,
              `imported_at`   DATETIME     NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_data_import_file` (`file_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function cacheFile(): string
    {
        return $this->basePath . '/storage/cache/data_imports.version';
    }

    private function readCache(): string
    {
        $file = $this->cacheFile();
        return is_file($file) ? trim((string) file_get_contents($file)) : '';
    }

    private function writeCache(string $value): void
    {
        @file_put_contents($this->cacheFile(), $value, LOCK_EX);
    }
}
