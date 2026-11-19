<?php
declare(strict_types=1);

namespace App\Services\DataImport;

use App\Core\Database;
use RuntimeException;
use Throwable;

/**
 * Teaching rooms and the blocks that hold them, from the university's own
 * list. A room is recognised by its code, so re-importing the file corrects
 * a capacity or a name rather than creating a second room.
 */
final class RoomImporter extends Importer
{
    private const TYPES = ['lecture_hall', 'laboratory', 'tutorial', 'exam_hall', 'office', 'library', 'other'];

    public function run(array $data, ImportReport $report, bool $dryRun = false): void
    {
        foreach ($data['rooms'] as $room) {
            if (!in_array($room['type'], self::TYPES, true)) {
                throw new RuntimeException("Unknown room type \"{$room['type']}\" for {$room['code']}.");
            }
            if (!isset($data['buildings'][$room['building']])) {
                throw new RuntimeException("Room {$room['code']} names a building \"{$room['building']}\" the file does not list.");
            }
        }

        $campusId = Database::scalar('SELECT id FROM campuses WHERE is_main = 1 ORDER BY id LIMIT 1')
            ?? Database::scalar('SELECT id FROM campuses ORDER BY id LIMIT 1');

        /* ---- The blocks ---- */
        $buildingIds = [];
        foreach ($data['buildings'] as $code => $building) {
            try {
                $this->begin();
                $existing = Database::selectOne('SELECT * FROM buildings WHERE code = ?', [$code]);
                if ($existing !== null) {
                    Database::statement(
                        'UPDATE buildings SET name = ?, floors = ?, status = "active" WHERE id = ?',
                        [$building['name'], $building['floors'], $existing['id']]
                    );
                    $buildingIds[$code] = (int) $existing['id'];
                    $action = 'updated';
                } else {
                    Database::statement(
                        'INSERT INTO buildings (campus_id, code, name, floors, status) VALUES (?, ?, ?, ?, "active")',
                        [$campusId, $code, $building['name'], $building['floors']]
                    );
                    $buildingIds[$code] = Database::lastInsertId();
                    $action = 'created';
                }
                $this->finish($dryRun);
                $action === 'created' ? $report->created++ : $report->updated++;
                $report->line(sprintf('%-7s building  %-6s %s', strtoupper($action), $code, $building['name']));
            } catch (Throwable $e) {
                $this->abort();
                $report->failed++;
                $report->line(sprintf('%-7s building  %-6s %s', 'FAILED', $code, $e->getMessage()));
            }
            if ($dryRun && !isset($buildingIds[$code])) {
                // The rollback undid the insert; the rooms below still need something to point at.
                $buildingIds[$code] = 0;
            }
        }

        /* ---- The rooms ---- */
        foreach ($data['rooms'] as $room) {
            try {
                $this->begin();
                $buildingId = $buildingIds[$room['building']] ?: null;
                $existing   = Database::selectOne('SELECT * FROM rooms WHERE code = ?', [$room['code']]);
                if ($existing !== null) {
                    Database::statement(
                        'UPDATE rooms SET building_id = ?, name = ?, floor = ?, capacity = ?, room_type = ?, has_projector = ? WHERE id = ?',
                        [$buildingId, $room['name'], $room['floor'], $room['capacity'], $room['type'],
                         (int) $room['projector'], $existing['id']]
                    );
                    $action = 'updated';
                } else {
                    Database::statement(
                        'INSERT INTO rooms (building_id, code, name, floor, capacity, room_type, has_projector, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, "available")',
                        [$buildingId, $room['code'], $room['name'], $room['floor'], $room['capacity'],
                         $room['type'], (int) $room['projector']]
                    );
                    $action = 'created';
                }
                $this->finish($dryRun);
                $action === 'created' ? $report->created++ : $report->updated++;
                $report->line(sprintf('%-7s %-14s %-28s %-13s seats %3d  %s', strtoupper($action), $room['code'], $room['name'],
                    str_replace('_', ' ', $room['type']), $room['capacity'], $room['building']));
                if ($room['note'] !== null) {
                    $report->note($room['note']);
                }
            } catch (Throwable $e) {
                $this->abort();
                $report->failed++;
                $report->line(sprintf('%-7s %-14s %s', 'FAILED', $room['code'], $e->getMessage()));
            }
        }

        $report->summary('Teaching rooms in the system', (string) (int) Database::scalar(
            'SELECT COUNT(*) FROM rooms WHERE status <> "unavailable"'
        ));
        $report->summary('Total seats', (string) (int) Database::scalar(
            'SELECT COALESCE(SUM(capacity), 0) FROM rooms WHERE status = "available"'
        ));
    }
}
