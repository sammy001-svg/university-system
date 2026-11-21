<?php
declare(strict_types=1);

namespace App\Services\Elearning;

use App\Core\Database;
use App\Core\Request;
use App\Support\Lookup;

/**
 * Shared scope for the academic office's view of online learning and online
 * examinations: which semester, department and search term the lists cover.
 *
 * Every condition is written against the aliases `o` (course_offerings) and
 * `c` (courses). Times are passed from PHP (the application's time zone), as
 * ExamService does, so phases here always agree with what lecturers and
 * students see.
 */
final class Oversight
{
    /** Extra SQL a page needs for its lecturer column. */
    public const LECTURER_JOIN = 'LEFT JOIN staff ls ON ls.id = o.lecturer_id LEFT JOIN users lu ON lu.id = ls.user_id';
    public const LECTURER_NAME = 'TRIM(CONCAT_WS(" ", lu.title, lu.first_name, lu.last_name))';
    public const REGISTERED    = '(SELECT COUNT(*) FROM course_registrations cr WHERE cr.offering_id = o.id AND cr.status <> "dropped")';

    /**
     * @return array{filters: array<string,mixed>, where: list<string>, params: list<mixed>}
     */
    public static function scope(Request $request, array $searchColumns = ['c.code', 'c.title']): array
    {
        // No semester in the query string means "the current semester"; an empty one means "all semesters".
        $semesterId = $request->has('semester')
            ? ($request->int('semester') ?: null)
            : (self::currentSemesterId());
        $departmentId = $request->int('department') ?: null;
        $q = trim((string) $request->query('q', ''));

        $where = ['o.status <> "cancelled"'];
        $params = [];
        if ($semesterId !== null) {
            $where[] = 'o.semester_id = ?';
            $params[] = $semesterId;
        }
        if ($departmentId !== null) {
            $where[] = 'c.department_id = ?';
            $params[] = $departmentId;
        }
        if ($q !== '') {
            $where[] = '(' . implode(' OR ', array_map(static fn (string $col): string => $col . ' LIKE ?', $searchColumns)) . ')';
            foreach ($searchColumns as $_) {
                $params[] = '%' . $q . '%';
            }
        }

        return [
            'filters' => ['semester' => $semesterId, 'department' => $departmentId, 'q' => $q],
            'where'   => $where,
            'params'  => $params,
        ];
    }

    public static function currentSemesterId(): ?int
    {
        $id = Database::scalar('SELECT id FROM semesters WHERE is_current = 1 ORDER BY id DESC LIMIT 1');
        return $id === null ? null : (int) $id;
    }

    /** Dropdown data every oversight page shows. */
    public static function lookups(): array
    {
        return ['semesters' => Lookup::semesters(), 'departments' => Lookup::departments()];
    }

    /** A paginate()-shaped result for a hand-written query, so paginate_links() works. */
    public static function paginate(string $select, string $from, array $params, int $page, int $perPage, string $orderBy, array $orderParams = []): array
    {
        $total = (int) Database::scalar('SELECT COUNT(*) FROM (SELECT 1 ' . $from . ') t', $params);
        $last  = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, $page), $last);
        $rows  = Database::select(
            $select . ' ' . $from . ' ORDER BY ' . $orderBy . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            array_merge($params, $orderParams)
        );
        return [
            'data' => $rows, 'total' => $total, 'page' => $page, 'last_page' => $last, 'per_page' => $perPage,
            'from' => $total === 0 ? 0 : ($page - 1) * $perPage + 1, 'to' => min($total, $page * $perPage),
        ];
    }

    public static function pct(float|int|null $part, float|int|null $whole): ?float
    {
        return (float) $whole > 0 ? round((float) $part / (float) $whole * 100, 1) : null;
    }
}

