<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Base model: table gateway with guarded mass assignment and audit hooks.
 */
abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    /** Columns that may be written from request input. */
    protected array $fillable = [];
    /** Columns never returned to the browser. */
    protected array $hidden = [];
    protected bool $timestamps = true;
    protected bool $softDeletes = false;
    /** Columns that a list screen may be searched on. */
    protected array $searchable = [];

    public function table(): string
    {
        return $this->table;
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    public function searchable(): array
    {
        return $this->searchable;
    }

    public function query(string $alias = ''): QueryBuilder
    {
        $qb = QueryBuilder::table($this->table, $alias);
        if ($this->softDeletes) {
            $prefix = $alias !== '' ? "{$alias}." : '';
            $qb->whereNull("{$prefix}deleted_at");
        }
        return $qb;
    }

    /** Query including soft-deleted rows. */
    public function withTrashed(string $alias = ''): QueryBuilder
    {
        return QueryBuilder::table($this->table, $alias);
    }

    public function find(int|string $id): ?array
    {
        return $this->query()->where($this->primaryKey, $id)->first();
    }

    public function findBy(string $column, mixed $value): ?array
    {
        return $this->query()->where($column, $value)->first();
    }

    public function all(string $orderBy = null): array
    {
        $qb = $this->query();
        if ($orderBy !== null) {
            $qb->orderBy($orderBy);
        }
        return $qb->get();
    }

    /** Filter an input array down to the fillable columns. */
    public function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    public function create(array $data, bool $guard = true): int
    {
        $data = $guard ? $this->filterFillable($data) : $data;
        if ($this->timestamps) {
            $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
            $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');
        }
        if ($data === []) {
            throw new \InvalidArgumentException('No insertable data supplied for ' . $this->table);
        }

        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql          = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->table,
            implode('`, `', $columns),
            $placeholders
        );
        Database::run($sql, array_values($data));
        return Database::lastInsertId();
    }

    public function update(int|string $id, array $data, bool $guard = true): int
    {
        $data = $guard ? $this->filterFillable($data) : $data;
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        if ($data === []) {
            return 0;
        }

        $assignments = implode(', ', array_map(static fn ($c) => "`{$c}` = ?", array_keys($data)));
        $sql         = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = ?',
            $this->table,
            $assignments,
            $this->primaryKey
        );
        $params   = array_values($data);
        $params[] = $id;
        return Database::statement($sql, $params);
    }

    public function updateWhere(array $conditions, array $data): int
    {
        if ($data === [] || $conditions === []) {
            return 0;
        }
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        $assignments = implode(', ', array_map(static fn ($c) => "`{$c}` = ?", array_keys($data)));
        $where       = implode(' AND ', array_map(static fn ($c) => "`{$c}` = ?", array_keys($conditions)));
        $sql         = "UPDATE `{$this->table}` SET {$assignments} WHERE {$where}";
        return Database::statement($sql, array_merge(array_values($data), array_values($conditions)));
    }

    public function delete(int|string $id): int
    {
        if ($this->softDeletes) {
            return Database::statement(
                "UPDATE `{$this->table}` SET `deleted_at` = ? WHERE `{$this->primaryKey}` = ?",
                [date('Y-m-d H:i:s'), $id]
            );
        }
        return Database::statement(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    public function forceDelete(int|string $id): int
    {
        return Database::statement(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    public function restore(int|string $id): int
    {
        return Database::statement(
            "UPDATE `{$this->table}` SET `deleted_at` = NULL WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    public function exists(string $column, mixed $value, int|string|null $exceptId = null): bool
    {
        $qb = $this->query()->where($column, $value);
        if ($exceptId !== null) {
            $qb->where($this->primaryKey, '!=', $exceptId);
        }
        return $qb->exists();
    }

    /** Options list for dropdowns. */
    public function options(string $labelColumn = 'name', ?string $orderBy = null, array $where = []): array
    {
        $qb = $this->query();
        foreach ($where as $column => $value) {
            $qb->where($column, $value);
        }
        $qb->orderBy($orderBy ?? $labelColumn);
        return $qb->pluck($labelColumn, $this->primaryKey);
    }

    public function hideFields(array $row): array
    {
        foreach ($this->hidden as $field) {
            unset($row[$field]);
        }
        return $row;
    }
}
