<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use PDO;
use Throwable;

/**
 * Keeps the database in step with the academic catalogue
 * (database/seeds/catalog/bbiu.php and its loader database/seeds/academic.php).
 *
 * syncIfChanged() runs on every request. While the catalogue is unchanged it
 * only compares a file fingerprint with storage/cache/academic_catalog.version
 * and touches nothing else. After an edit, the first request upgrades the
 * schema, reloads the catalogue in a transaction and records the fingerprint
 * in the settings table, so the reload happens once per change.
 */
final class AcademicCatalogSync
{
    private const SETTING_KEY   = 'academic_catalog_version';
    private const LOCK_NAME     = 'ums_academic_catalog_sync';
    private const RETRY_SECONDS = 600;

    public function __construct(private string $basePath)
    {
    }

    /** Fingerprint of the catalogue data and the loader that applies it. */
    public function fingerprint(): string
    {
        return sha1(
            (string) sha1_file($this->basePath . '/database/seeds/catalog/bbiu.php')
            . (string) sha1_file($this->basePath . '/database/seeds/academic.php')
        );
    }

    /**
     * Called once per request; never throws, so a failed sync cannot take the site down.
     * $connect supplies the PDO connection and is only called when a sync may be due.
     */
    public function syncIfChanged(callable $connect): void
    {
        try {
            $fingerprint = $this->fingerprint();
            $cached      = $this->readCache();
            if ($cached === 'ok:' . $fingerprint) {
                return;
            }
            // A recent failure for this same version: wait before trying again.
            if (preg_match('/^failed:' . $fingerprint . ':(\d+)$/', $cached, $m) && time() - (int) $m[1] < self::RETRY_SECONDS) {
                return;
            }
            /** @var PDO $pdo */
            $pdo = $connect();
            // Another server or the CLI importer may already have applied this version.
            if ($this->recordedVersion($pdo) === $fingerprint) {
                $this->writeCache('ok:' . $fingerprint);
                return;
            }

            // One request applies it; the others carry on with the current data.
            if ((int) $pdo->query("SELECT GET_LOCK('" . self::LOCK_NAME . "', 0)")->fetchColumn() !== 1) {
                return;
            }
            try {
                if ($this->recordedVersion($pdo) !== $fingerprint) {
                    $noCurrentSemester = (int) $pdo->query('SELECT COUNT(*) FROM semesters WHERE is_current = 1')->fetchColumn() === 0;
                    $stats = $this->load($pdo, $noCurrentSemester);
                    Logger::info('Academic catalogue synchronised', $stats + ['version' => $fingerprint]);
                }
                $this->writeCache('ok:' . $fingerprint);
            } finally {
                $pdo->query("SELECT RELEASE_LOCK('" . self::LOCK_NAME . "')");
            }
        } catch (Throwable $e) {
            Logger::error('Academic catalogue sync failed: ' . $e->getMessage());
            $this->writeCache('failed:' . ($fingerprint ?? '') . ':' . time());
        }
    }

    /**
     * Upgrade the schema, load the catalogue in one transaction and record its
     * fingerprint. $extra runs inside the same transaction (the CLI importer
     * uses it to retire records that are not in the catalogue).
     */
    public function load(PDO $pdo, bool $setCurrent = false, ?callable $extra = null): array
    {
        $this->upgradeSchema($pdo); // DDL commits implicitly, so it runs before the transaction.

        $academic = require $this->basePath . '/database/seeds/academic.php';
        $pdo->beginTransaction();
        try {
            $stats = $academic($pdo, ['set_current' => $setCurrent]);
            if ($extra !== null) {
                $stats += (array) $extra($pdo);
            }
            $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value, setting_group, data_type, label)
                 VALUES (?, ?, "system", "string", "Academic catalogue version")
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            )->execute([self::SETTING_KEY, $this->fingerprint()]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $this->writeCache('ok:' . $this->fingerprint());
        return $stats;
    }

    /** Bring an older database up to the catalogue's schema. Safe to run repeatedly. */
    public function upgradeSchema(PDO $pdo): void
    {
        $count = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();
        $hasColumn = static fn (string $table, string $column): bool => $count(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = '{$column}'"
        ) > 0;
        $hasIndex = static fn (string $table, string $index): bool => $count(
            "SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = '{$table}' AND index_name = '{$index}'"
        ) > 0;
        $hasForeignKey = static fn (string $table, string $name): bool => $count(
            "SELECT COUNT(*) FROM information_schema.table_constraints
              WHERE table_schema = DATABASE() AND table_name = '{$table}' AND constraint_name = '{$name}' AND constraint_type = 'FOREIGN KEY'"
        ) > 0;

        $levelType = (string) $pdo->query(
            "SELECT column_type FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'programs' AND column_name = 'level'"
        )->fetchColumn();
        if (!str_contains($levelType, "'associate'")) {
            $pdo->exec("ALTER TABLE programs MODIFY `level`
                        ENUM('certificate','diploma','associate','bachelor','postgraduate_diploma','masters','phd') NOT NULL DEFAULT 'bachelor'");
        }

        if (!$hasColumn('program_courses', 'credit_hours')) {
            if ($hasForeignKey('program_courses', 'fk_pc_course')) {
                $pdo->exec('ALTER TABLE program_courses DROP FOREIGN KEY fk_pc_course');
            }
            if (!$hasIndex('program_courses', 'ix_pc_plan')) {
                $pdo->exec('ALTER TABLE program_courses ADD KEY ix_pc_plan (program_id, year_of_study, semester_number)');
            }
            if ($hasIndex('program_courses', 'uq_program_course')) {
                $pdo->exec('ALTER TABLE program_courses DROP INDEX uq_program_course');
            }
            $pdo->exec(
                "ALTER TABLE program_courses
                   MODIFY course_id INT UNSIGNED DEFAULT NULL,
                   ADD COLUMN slot_label VARCHAR(120) DEFAULT NULL AFTER course_id,
                   ADD COLUMN credit_hours TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER semester_number,
                   MODIFY course_type ENUM('core','elective','common','audit','minor','concentration') NOT NULL DEFAULT 'core',
                   ADD COLUMN choice_group TINYINT UNSIGNED DEFAULT NULL AFTER course_type,
                   ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER choice_group,
                   ADD COLUMN note VARCHAR(255) DEFAULT NULL AFTER sort_order,
                   ADD UNIQUE KEY uq_program_course (program_id, year_of_study, semester_number, course_id),
                   ADD CONSTRAINT fk_pc_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE"
            );
            // Existing rows keep the course's own credit hours.
            $pdo->exec('UPDATE program_courses pc JOIN courses c ON c.id = pc.course_id SET pc.credit_hours = c.credit_hours');
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS program_course_options (
               id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
               program_id   INT UNSIGNED NOT NULL,
               list_type    ENUM('elective','minor','concentration') NOT NULL,
               group_label  VARCHAR(120)          DEFAULT NULL,
               course_id    INT UNSIGNED          DEFAULT NULL,
               slot_label   VARCHAR(120)          DEFAULT NULL,
               credit_hours TINYINT UNSIGNED NOT NULL DEFAULT 3,
               choice_group TINYINT UNSIGNED      DEFAULT NULL,
               sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
               note         VARCHAR(255)          DEFAULT NULL,
               PRIMARY KEY (id),
               KEY ix_pco_program (program_id, list_type, sort_order),
               CONSTRAINT fk_pco_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE CASCADE,
               CONSTRAINT fk_pco_course  FOREIGN KEY (course_id)  REFERENCES courses (id)  ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Installment billing and semester enrolment (database/migrations/2026_10_26_billing_installments.sql).
        if (!$hasColumn('fee_structures', 'student_status')) {
            $sql = (string) file_get_contents($this->basePath . '/database/migrations/2026_10_26_billing_installments.sql');
            foreach (explode(";\n", str_replace("\r\n", "\n", $sql)) as $chunk) {
                $statement = trim((string) preg_replace('/^\s*--.*$/m', '', $chunk));
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }
        }

        $columnType = static fn (string $table, string $column): string => (string) $pdo->query(
            "SELECT CONCAT(column_type, '|', is_nullable) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = '{$table}' AND column_name = '{$column}'"
        )->fetchColumn();

        // Students on paper billing forms have no e-mail address.
        if (str_ends_with($columnType('users', 'email'), '|NO')) {
            $pdo->exec('ALTER TABLE users MODIFY email VARCHAR(150) DEFAULT NULL');
        }

        // Per-credit tuition, billing-form course codes, and the official fee schedules.
        if (!$hasColumn('courses', 'tuition_rate_code')) {
            $pdo->exec('ALTER TABLE courses ADD COLUMN tuition_rate_code VARCHAR(20) DEFAULT NULL AFTER level');
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS tuition_rates (
               id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
               code            VARCHAR(20)  NOT NULL,
               name            VARCHAR(120) NOT NULL,
               rate_per_credit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
               description     VARCHAR(255)          DEFAULT NULL,
               status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
               PRIMARY KEY (id),
               UNIQUE KEY uq_tuition_rate_code (code)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS course_aliases (
               id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
               code         VARCHAR(25)  NOT NULL,
               course_id    INT UNSIGNED NOT NULL,
               title        VARCHAR(200)          DEFAULT NULL,
               credit_hours TINYINT UNSIGNED      DEFAULT NULL,
               source       VARCHAR(80)           DEFAULT NULL,
               note         VARCHAR(255)          DEFAULT NULL,
               PRIMARY KEY (id),
               UNIQUE KEY uq_course_alias (code),
               KEY ix_alias_course (course_id),
               CONSTRAINT fk_alias_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        if (!$hasColumn('fee_structures', 'code')) {
            $pdo->exec('ALTER TABLE fee_structures ADD COLUMN code VARCHAR(40) DEFAULT NULL AFTER id, ADD UNIQUE KEY uq_fs_code (code)');
        }
        if (!$hasColumn('fee_structures', 'tuition_basis')) {
            $pdo->exec("ALTER TABLE fee_structures ADD COLUMN tuition_basis ENUM('flat','per_credit') NOT NULL DEFAULT 'flat' AFTER student_status");
        }
        if (!str_contains($columnType('fee_structures', 'student_status'), 'first_term')) {
            $pdo->exec("ALTER TABLE fee_structures MODIFY student_status
                        ENUM('all','new','continuing','first_term','second_term') NOT NULL DEFAULT 'all'");
        }
        if (!$hasColumn('course_registrations', 'credit_hours')) {
            $pdo->exec('ALTER TABLE course_registrations ADD COLUMN credit_hours TINYINT UNSIGNED DEFAULT NULL AFTER registration_type');
        }
        // Staff can belong to a college without (yet) a department.
        if (!$hasColumn('staff', 'faculty_id')) {
            $pdo->exec(
                'ALTER TABLE staff ADD COLUMN faculty_id INT UNSIGNED DEFAULT NULL AFTER department_id,
                   ADD KEY ix_staff_faculty (faculty_id),
                   ADD CONSTRAINT fk_staff_faculty FOREIGN KEY (faculty_id) REFERENCES faculties (id) ON DELETE SET NULL'
            );
            $pdo->exec('UPDATE staff s JOIN departments d ON d.id = s.department_id SET s.faculty_id = d.faculty_id');
        }
        if (!$hasColumn('invoice_items', 'course_id')) {
            $pdo->exec('ALTER TABLE invoice_items ADD COLUMN course_id INT UNSIGNED DEFAULT NULL AFTER fee_type_id');
        }
    }

    private function recordedVersion(PDO $pdo): ?string
    {
        $statement = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
        $statement->execute([self::SETTING_KEY]);
        $value = $statement->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    private function cacheFile(): string
    {
        return $this->basePath . '/storage/cache/academic_catalog.version';
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
