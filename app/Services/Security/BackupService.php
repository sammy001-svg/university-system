<?php
declare(strict_types=1);

namespace App\Services\Security;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Setting;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Database backups to storage/backups as gzipped SQL. Uses mysqldump when
 * MYSQLDUMP_PATH is set, otherwise writes the dump in PHP (fine for a
 * university-sized database). Runs daily by itself (after a page has been
 * delivered) and on demand from Admin > Backups or `php database/backup.php`.
 *
 * Restore with:  gunzip < backup.sql.gz | mysql -u USER -p DATABASE
 */
final class BackupService
{
    private const NAME_PATTERN = '/^backup-\d{8}-\d{6}\.sql\.gz$/';

    public static function dir(): string
    {
        $dir = Config::get('paths.storage') . '/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }
        return $dir;
    }

    /** @return list<array{name:string,size:int,time:int}> newest first */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/backup-*.sql.gz') ?: [] as $file) {
            $out[] = ['name' => basename($file), 'size' => (int) filesize($file), 'time' => (int) filemtime($file)];
        }
        usort($out, static fn (array $a, array $b): int => $b['time'] <=> $a['time']);
        return $out;
    }

    public static function path(string $name): string
    {
        if (!preg_match(self::NAME_PATTERN, $name) || !is_file(self::dir() . '/' . $name)) {
            throw new RuntimeException('Backup not found.');
        }
        return self::dir() . '/' . $name;
    }

    /** Write a new backup; returns its file name. */
    public static function create(): string
    {
        @set_time_limit(0);
        $name = 'backup-' . date('Ymd-His') . '.sql.gz';
        $file = self::dir() . '/' . $name;
        $db   = (array) Config::get('db');
        $dump = (string) Config::get('backup.mysqldump', '');

        if ($dump !== '' && is_file($dump)) {
            $cmd = escapeshellarg($dump) . ' --single-transaction --routines --default-character-set=utf8mb4'
                 . ' -h ' . escapeshellarg((string) $db['host']) . ' -P ' . (int) $db['port']
                 . ' -u ' . escapeshellarg((string) $db['user']) . ' ' . escapeshellarg((string) $db['name']);
            $env = ['MYSQL_PWD' => (string) $db['pass']];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + getenv());
            if (is_resource($proc)) {
                $gz = gzopen($file, 'wb6');
                while (!feof($pipes[1])) {
                    gzwrite($gz, (string) fread($pipes[1], 1 << 16));
                }
                gzclose($gz);
                $err = stream_get_contents($pipes[2]);
                if (proc_close($proc) === 0) {
                    self::prune();
                    return $name;
                }
                @unlink($file);
                Logger::error('mysqldump failed, falling back to PHP: ' . $err);
            }
        }

        $pdo = Database::connection();
        $gz  = gzopen($file, 'wb6');
        try {
            gzwrite($gz, "-- " . Config::get('app.name') . " database backup\n-- Created " . date('Y-m-d H:i:s') . "\n\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
            foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM) as [$table]) {
                $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM)[1];
                gzwrite($gz, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");
                $offset = 0;
                do {
                    $rows = $pdo->query('SELECT * FROM `' . $table . '` LIMIT 500 OFFSET ' . $offset)->fetchAll(PDO::FETCH_ASSOC);
                    if ($rows !== []) {
                        $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
                        $values  = [];
                        foreach ($rows as $row) {
                            $values[] = '(' . implode(', ', array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row)) . ')';
                        }
                        gzwrite($gz, "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $values) . ";\n");
                    }
                    $offset += 500;
                } while (count($rows) === 500);
                gzwrite($gz, "\n");
            }
            gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
            gzclose($gz);
        } catch (Throwable $e) {
            gzclose($gz);
            @unlink($file);
            throw $e;
        }
        self::prune();
        return $name;
    }

    /** Keep the newest N backups (Settings > Security). */
    public static function prune(): void
    {
        $keep = max(1, (int) Setting::get('backup_keep', 14));
        foreach (array_slice(self::all(), $keep) as $old) {
            @unlink(self::dir() . '/' . $old['name']);
        }
    }

    /** Called at the end of a request: one backup a day when Settings > Security asks for it. */
    public static function dailyIfDue(): void
    {
        try {
            if (!(bool) Setting::get('backup_auto_daily', true)) {
                return;
            }
            $marker = Config::get('paths.storage') . '/cache/backup.last';
            if (is_file($marker) && time() - (int) file_get_contents($marker) < 86400) {
                return;
            }
            $latest = self::all()[0]['time'] ?? 0;
            if (time() - $latest < 86400) {
                @file_put_contents($marker, (string) $latest);
                return;
            }
            if ((int) Database::scalar("SELECT GET_LOCK('ums_backup', 0)") !== 1) {
                return;
            }
            try {
                @file_put_contents($marker, (string) time());
                $name = self::create();
                Logger::write('info', 'Daily backup written: ' . $name);
            } finally {
                Database::scalar("SELECT RELEASE_LOCK('ums_backup')");
            }
        } catch (Throwable $e) {
            Logger::error('Daily backup failed: ' . $e->getMessage());
        }
    }
}
