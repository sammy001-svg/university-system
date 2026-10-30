<?php
declare(strict_types=1);

namespace App\Services\DataImport;

use App\Core\Database;

/**
 * Base for the data-file importers (students, lecturers, staff). Each record
 * is saved in its own transaction so one bad row never blocks the rest; a dry
 * run rolls every record back.
 *
 * Accounts are created without a usable password ("!" never verifies): a
 * Super Admin issues first-time passwords from Admin > Data Imports > Login
 * slips. That keeps imports fast enough to run during a page load.
 */
abstract class Importer
{
    /** Stored as the password of accounts that have not been issued one yet. */
    public const NO_PASSWORD = '!';

    abstract public function run(array $data, ImportReport $report, bool $dryRun = false): void;

    protected function begin(): void
    {
        Database::beginTransaction();
    }

    protected function finish(bool $dryRun): void
    {
        $dryRun ? Database::rollBack() : Database::commit();
    }

    protected function abort(): void
    {
        Database::rollBack();
    }

    protected static function slug(?string $s): string
    {
        return (string) preg_replace('/[^a-z]/', '', strtolower(str_replace(['Jr.', 'Sr.'], '', (string) $s)));
    }

    /** $base, or $base2, $base3 ... - the first username nobody has. */
    protected static function freeUsername(string $base): string
    {
        for ($n = 2, $candidate = $base; Database::scalar('SELECT id FROM users WHERE username = ?', [$candidate]); $n++) {
            $candidate = $base . $n;
        }
        return $candidate;
    }

    /** Next staff number in the Staff screen's format: <institution short name>/0001. */
    protected static function nextStaffNumber(): string
    {
        $prefix = (string) (Database::scalar('SELECT setting_value FROM settings WHERE setting_key = "institution_short_name"') ?: 'STF');
        $max    = (int) Database::scalar(
            'SELECT COALESCE(MAX(CAST(SUBSTRING(staff_number, ?) AS UNSIGNED)), 0) FROM staff WHERE staff_number REGEXP ?',
            [strlen($prefix) + 2, '^' . preg_quote($prefix, '/') . '/[0-9]+$']
        );
        return $prefix . '/' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** A new account with no password yet; the person must set one at first sign-in. */
    protected static function createAccount(string $username, array $person, string $userType, bool $canSignIn = true): int
    {
        Database::statement(
            'INSERT INTO users (uuid, username, email, password_hash, title, first_name, last_name, other_name, gender, phone,
                                user_type, status, must_change_password)
             VALUES (UUID(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [$username, $person['email'] ?? null, self::NO_PASSWORD, $person['title'] ?? null, $person['first'], $person['last'],
             $person['other'] ?? null, $person['sex'] ?? null, $person['phone'] ?? null, $userType, $canSignIn ? 'active' : 'inactive']
        );
        return Database::lastInsertId();
    }

    protected static function grantRole(int $userId, ?string $slug): void
    {
        if ($slug !== null) {
            Database::statement(
                'INSERT IGNORE INTO user_roles (user_id, role_id) SELECT ?, id FROM roles WHERE slug = ?',
                [$userId, $slug]
            );
        }
    }
}
