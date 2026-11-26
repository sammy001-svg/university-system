<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Acl;
use App\Core\Database;
use App\Core\Hash;
use App\Core\Model;
use App\Core\QueryBuilder;

final class User extends Model
{
    protected string $table = 'users';
    protected bool $softDeletes = true;
    protected array $hidden = ['password_hash', 'remember_token'];
    protected array $fillable = [
        'uuid', 'username', 'email', 'password_hash', 'title', 'first_name', 'last_name', 'other_name',
        'gender', 'date_of_birth', 'phone', 'alt_phone', 'avatar', 'user_type', 'status',
        'must_change_password', 'email_verified_at', 'created_by',
    ];
    protected array $searchable = ['username', 'email', 'first_name', 'last_name', 'phone'];

    /** Base listing query with the user's roles rolled up into one column. */
    public function listing(): QueryBuilder
    {
        return $this->query('u')
            ->select(
                'u.id', 'u.username', 'u.email', 'u.first_name', 'u.last_name', 'u.title',
                'u.phone', 'u.avatar', 'u.user_type', 'u.status', 'u.last_login_at', 'u.created_at',
                "GROUP_CONCAT(DISTINCT r.name ORDER BY r.level SEPARATOR ', ') AS roles"
            )
            ->leftJoin('user_roles ur', 'ur.user_id = u.id')
            ->leftJoin('roles r', 'r.id = ur.role_id')
            ->groupBy('u.id');
    }

    /** Create a user account and assign roles in one transaction. */
    public function createAccount(array $data, array $roleIds = [], ?string $plainPassword = null, ?int $actorId = null): int
    {
        return Database::transaction(function () use ($data, $roleIds, $plainPassword, $actorId) {
            $password              = $plainPassword ?? Hash::tempPassword();
            $data['uuid']          = Hash::uuid4();
            $data['password_hash'] = Hash::make($password);
            $data['created_by']    = $actorId;
            $data['status']        = $data['status'] ?? 'active';

            $userId = $this->create($data);
            if ($roleIds !== []) {
                Acl::syncRoles($userId, $roleIds, $actorId);
            }
            return $userId;
        });
    }

    public function setPassword(int $userId, string $plain, bool $mustChange = false): void
    {
        Database::statement(
            'UPDATE users SET password_hash = ?, must_change_password = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?',
            [Hash::make($plain), $mustChange ? 1 : 0, $userId]
        );
    }

    public function fullName(array $user): string
    {
        return trim(implode(' ', array_filter([
            $user['title'] ?? null,
            $user['first_name'] ?? null,
            $user['other_name'] ?? null,
            $user['last_name'] ?? null,
        ])));
    }

    /** Build a unique username from a person's name. */
    public function generateUsername(string $first, string $last): string
    {
        $base = strtolower(preg_replace('/[^a-z]/i', '', substr($first, 0, 1) . $last));
        $base = $base === '' ? 'user' : substr($base, 0, 20);
        $candidate = $base;
        $suffix    = 0;
        while ($this->withTrashed()->where('username', $candidate)->exists()) {
            $candidate = $base . (++$suffix);
        }
        return $candidate;
    }

    public function roleIds(int $userId): array
    {
        return array_map(
            'intval',
            array_column(Database::select('SELECT role_id FROM user_roles WHERE user_id = ?', [$userId]), 'role_id')
        );
    }

    /** Users eligible to be picked as lecturers/approvers. */
    /**
     * Staff who teach, for a lecturer picker: academic staff only, so a class is
     * never handed to a janitor by a slip of the mouse. Each is labelled with the
     * college they are posted to.
     *
     * @return array<int,string> staff id => label
     */
    public function teachingStaffOptions(): array
    {
        $rows = Database::select(
            "SELECT s.id, s.staff_number, s.designation,
                    CONCAT_WS(' ', COALESCE(u.title, ''), u.first_name, u.last_name) AS name,
                    COALESCE(f.name, d.name, '') AS posting
               FROM staff s
               JOIN users u      ON u.id = s.user_id
          LEFT JOIN faculties f  ON f.id = s.faculty_id
          LEFT JOIN departments d ON d.id = s.department_id
              WHERE s.status = 'active' AND u.deleted_at IS NULL AND s.staff_category = 'academic'
              ORDER BY u.last_name, u.first_name"
        );
        $out = [];
        foreach ($rows as $row) {
            $label = trim((string) preg_replace('/\s+/', ' ', $row['name']));
            if ($row['posting'] !== '') {
                $label .= ' - ' . $row['posting'];
            }
            $out[(int) $row['id']] = $label;
        }
        return $out;
    }

    public function staffOptions(): array
    {
        $rows = Database::select(
            "SELECT s.id, CONCAT(COALESCE(u.title,''), ' ', u.first_name, ' ', u.last_name, ' (', s.staff_number, ')') AS label
               FROM staff s JOIN users u ON u.id = s.user_id
              WHERE s.status = 'active' ORDER BY u.last_name"
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['id']] = trim($row['label']);
        }
        return $out;
    }
}
