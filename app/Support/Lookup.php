<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/** id => label lists for form dropdowns. */
final class Lookup
{
    public static function staff(): array
    {
        return array_column(Database::select(
            'SELECT s.id, CONCAT(u.last_name, ", ", u.first_name, COALESCE(CONCAT(" (", s.designation, ")"), "")) AS label
               FROM staff s JOIN users u ON u.id = s.user_id
              WHERE s.status = "active" AND u.deleted_at IS NULL ORDER BY u.last_name, u.first_name'
        ), 'label', 'id');
    }

    public static function departments(): array
    {
        return array_column(Database::select('SELECT id, name FROM departments WHERE status = "active" ORDER BY name'), 'name', 'id');
    }

    public static function rooms(): array
    {
        return array_column(Database::select('SELECT id, CONCAT(code, " - ", name) AS label FROM rooms ORDER BY code'), 'label', 'id');
    }

    public static function semesters(): array
    {
        return array_column(Database::select(
            'SELECT s.id, CONCAT(ay.name, " ", s.name) AS label FROM semesters s JOIN academic_years ay ON ay.id = s.academic_year_id
              ORDER BY ay.start_date DESC, s.semester_number DESC'
        ), 'label', 'id');
    }

    public static function academicYears(): array
    {
        return array_column(Database::select('SELECT id, name FROM academic_years ORDER BY start_date DESC'), 'name', 'id');
    }

    public static function programs(): array
    {
        return array_column(Database::select('SELECT id, CONCAT(code, " - ", name) AS label FROM programs WHERE status = "active" ORDER BY name'), 'label', 'id');
    }

    /** The signed-in user's staff id, or null. */
    public static function myStaffId(): ?int
    {
        $id = Database::scalar('SELECT id FROM staff WHERE user_id = ?', [\App\Core\Auth::id()]);
        return $id === null ? null : (int) $id;
    }
}
