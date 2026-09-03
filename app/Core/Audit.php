<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    public static function log(
        string $action,
        ?string $module = null,
        ?string $entity = null,
        ?string $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            Database::statement(
                'INSERT INTO audit_logs
                    (user_id, action, module, entity, entity_id, description, old_values, new_values, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    Auth::id(),
                    $action,
                    $module,
                    $entity,
                    $entityId,
                    $description,
                    $oldValues === null ? null : json_encode(self::scrub($oldValues)),
                    $newValues === null ? null : json_encode(self::scrub($newValues)),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                ]
            );
        } catch (\Throwable $e) {
            // Auditing must never break the request it is recording.
            Logger::error('Audit write failed: ' . $e->getMessage());
        }
    }

    /** Never store secrets in the audit trail. */
    private static function scrub(array $values): array
    {
        foreach (['password', 'password_hash', 'password_confirmation', 'remember_token', '_token'] as $key) {
            unset($values[$key]);
        }
        return $values;
    }

    public static function created(string $entity, int|string $id, array $values = [], ?string $module = null): void
    {
        self::log('create', $module, $entity, (string) $id, "Created {$entity} #{$id}", null, $values);
    }

    public static function updated(string $entity, int|string $id, array $old = [], array $new = [], ?string $module = null): void
    {
        self::log('update', $module, $entity, (string) $id, "Updated {$entity} #{$id}", $old, $new);
    }

    public static function deleted(string $entity, int|string $id, array $old = [], ?string $module = null): void
    {
        self::log('delete', $module, $entity, (string) $id, "Deleted {$entity} #{$id}", $old, null);
    }
}
