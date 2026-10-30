<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/** Matching people by name across staff lists that write middle names differently. */
final class PersonName
{
    /** Middle names agree when equal, or when one gives only initials of the other ("P." = "Partee"). */
    public static function sameMiddle(?string $a, ?string $b): bool
    {
        $words = static fn (?string $s): array => array_values(array_filter(preg_split('/[\s.]+/', strtolower((string) $s)) ?: []));
        [$x, $y] = [$words($a), $words($b)];
        if ($x === $y) {
            return true;
        }
        if ($x === [] || $y === [] || count($x) !== count($y)) {
            return false;
        }
        foreach ($x as $i => $w) {
            $v = $y[$i];
            if ($w !== $v && !((strlen($w) === 1 || strlen($v) === 1) && $w[0] === $v[0])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Staff records for this person: same first and last name, compatible middle names.
     * @return list<array<string,mixed>>
     */
    public static function findStaff(string $first, string $last, ?string $other): array
    {
        $candidates = Database::select(
            'SELECT s.id AS staff_id, u.id AS user_id, u.username, u.other_name, u.status, s.staff_number,
                    s.designation, s.staff_category
               FROM staff s JOIN users u ON u.id = s.user_id
              WHERE LOWER(u.first_name) = LOWER(?) AND LOWER(u.last_name) = LOWER(?) AND u.deleted_at IS NULL',
            [$first, $last]
        );
        return array_values(array_filter($candidates, static fn (array $c): bool => self::sameMiddle($c['other_name'], $other)));
    }
}
