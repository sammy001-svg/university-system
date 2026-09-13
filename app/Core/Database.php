<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO singleton with query helpers. All statements are prepared.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $queryCount = 0;
    private static array $queryLog = [];

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg = Config::get('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
            ]);
        } catch (PDOException $e) {
            // A missing environment file is by far the most common cause:
            // config.php then falls back to root/no-password and MySQL rejects
            // the connection. Report each candidate path and why it was passed
            // over, so the difference between "not there" and "there but not
            // readable" is visible without shell access.
            $found = function_exists('ums_env_path') ? ums_env_path() : null;

            if ($found !== null) {
                $hint = ' (check the DB_ credentials in ' . $found . ' and that MySQL is running)';
            } else {
                $lines = [];
                foreach (function_exists('ums_env_candidates') ? ums_env_candidates() : [] as $candidate) {
                    if (!file_exists($candidate)) {
                        $why = 'does not exist';
                    } elseif (!is_file($candidate)) {
                        $why = 'exists but is not a regular file';
                    } elseif (!is_readable($candidate)) {
                        $why = 'exists but PHP cannot read it -- check permissions/ownership';
                    } else {
                        $why = 'unreadable';
                    }
                    $lines[] = $candidate . ' (' . $why . ')';
                }
                $hint = ' -- no environment file found, so the fallback credentials in'
                      . ' config/config.php were used. Checked: ' . implode('; ', $lines)
                      . '. Create one of those from .env.example and set DB_NAME, DB_USER and DB_PASS.';
            }

            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage() . $hint,
                (int) $e->getCode()
            );
        }

        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $start = microtime(true);
        $stmt  = self::connection()->prepare($sql);
        $stmt->execute($params);
        self::$queryCount++;
        if (Config::get('app.debug')) {
            self::$queryLog[] = [
                'sql'    => $sql,
                'params' => $params,
                'time'   => round((microtime(true) - $start) * 1000, 2),
            ];
        }
        return $stmt;
    }

    /** @return array<int,array<string,mixed>> */
    public static function select(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function statement(string $sql, array $params = []): int
    {
        return self::run($sql, $params)->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::connection()->lastInsertId();
    }

    public static function beginTransaction(): void
    {
        if (!self::connection()->inTransaction()) {
            self::connection()->beginTransaction();
        }
    }

    public static function commit(): void
    {
        if (self::connection()->inTransaction()) {
            self::connection()->commit();
        }
    }

    public static function rollBack(): void
    {
        if (self::connection()->inTransaction()) {
            self::connection()->rollBack();
        }
    }

    /** Run a callback inside a transaction, rolling back on any exception. */
    public static function transaction(callable $callback): mixed
    {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function queryCount(): int
    {
        return self::$queryCount;
    }

    public static function queryLog(): array
    {
        return self::$queryLog;
    }
}
