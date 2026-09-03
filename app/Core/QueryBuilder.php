<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Small fluent SQL builder. Identifiers are supplied by application code,
 * every value is bound as a parameter.
 */
class QueryBuilder
{
    protected string $table;
    protected string $alias = '';
    protected array $columns = ['*'];
    protected array $joins = [];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $groups = [];
    protected array $havings = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(string $table, string $alias = '')
    {
        $this->table = $table;
        $this->alias = $alias;
    }

    public static function table(string $table, string $alias = ''): static
    {
        return new static($table, $alias);
    }

    public function select(string ...$columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function addSelect(string $column): static
    {
        if ($this->columns === ['*']) {
            $this->columns = [];
        }
        $this->columns[] = $column;
        return $this;
    }

    public function join(string $table, string $condition, string $type = 'INNER'): static
    {
        $this->joins[] = strtoupper($type) . " JOIN {$table} ON {$condition}";
        return $this;
    }

    public function leftJoin(string $table, string $condition): static
    {
        return $this->join($table, $condition, 'LEFT');
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value    = $operator;
            $operator = '=';
        }
        $this->wheres[]   = "{$column} {$operator} ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->wheres[] = "({$sql})";
        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        if ($values === []) {
            $this->wheres[] = '1 = 0';
            return $this;
        }
        $placeholders   = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} IN ({$placeholders})";
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->wheres[] = "{$column} IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->wheres[] = "{$column} IS NOT NULL";
        return $this;
    }

    public function whereBetween(string $column, mixed $from, mixed $to): static
    {
        $this->wheres[]   = "{$column} BETWEEN ? AND ?";
        $this->bindings[] = $from;
        $this->bindings[] = $to;
        return $this;
    }

    /** LIKE search across several columns (search boxes). */
    public function search(?string $term, array $columns): static
    {
        $term = trim((string) $term);
        if ($term === '' || $columns === []) {
            return $this;
        }
        $parts = [];
        foreach ($columns as $column) {
            $parts[]          = "{$column} LIKE ?";
            $this->bindings[] = '%' . $term . '%';
        }
        $this->wheres[] = '(' . implode(' OR ', $parts) . ')';
        return $this;
    }

    public function groupBy(string ...$columns): static
    {
        foreach ($columns as $column) {
            $this->groups[] = $column;
        }
        return $this;
    }

    public function having(string $sql, array $bindings = []): static
    {
        $this->havings[] = $sql;
        foreach ($bindings as $binding) {
            $this->bindings[] = $binding;
        }
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction      = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "{$column} {$direction}";
        return $this;
    }

    public function orderByRaw(string $sql): static
    {
        $this->orders[] = $sql;
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    public function toSql(): string
    {
        $from = $this->alias !== '' ? "{$this->table} AS {$this->alias}" : $this->table;
        $sql  = 'SELECT ' . implode(', ', $this->columns) . " FROM {$from}";

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }
        if ($this->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        if ($this->havings !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }
        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }
        return $sql;
    }

    public function bindings(): array
    {
        return $this->bindings;
    }

    public function get(): array
    {
        return Database::select($this->toSql(), $this->bindings);
    }

    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit(1);
        return Database::selectOne($clone->toSql(), $clone->bindings);
    }

    public function value(string $column): mixed
    {
        $clone          = clone $this;
        $clone->columns = [$column];
        $clone->limit(1);
        return Database::scalar($clone->toSql(), $clone->bindings);
    }

    /** Key/value list, handy for <select> options. */
    public function pluck(string $valueColumn, string $keyColumn = 'id'): array
    {
        $clone          = clone $this;
        $clone->columns = ["{$keyColumn} AS pk", "{$valueColumn} AS label"];
        $out            = [];
        foreach (Database::select($clone->toSql(), $clone->bindings) as $row) {
            $out[$row['pk']] = $row['label'];
        }
        return $out;
    }

    public function count(string $column = '*'): int
    {
        $clone          = clone $this;
        $clone->columns = ["COUNT({$column}) AS aggregate"];
        $clone->orders  = [];
        $clone->limit   = null;
        $clone->offset  = null;
        if ($clone->groups !== []) {
            $sub = $clone->toSql();
            return (int) Database::scalar("SELECT COUNT(*) FROM ({$sub}) AS grouped", $clone->bindings);
        }
        return (int) Database::scalar($clone->toSql(), $clone->bindings);
    }

    public function sum(string $column): float
    {
        $clone          = clone $this;
        $clone->columns = ["COALESCE(SUM({$column}), 0) AS aggregate"];
        $clone->orders  = [];
        $clone->limit   = null;
        $clone->offset  = null;
        return (float) Database::scalar($clone->toSql(), $clone->bindings);
    }

    public function avg(string $column): float
    {
        $clone          = clone $this;
        $clone->columns = ["COALESCE(AVG({$column}), 0) AS aggregate"];
        $clone->orders  = [];
        $clone->limit   = null;
        $clone->offset  = null;
        return (float) Database::scalar($clone->toSql(), $clone->bindings);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /** @return array{data:array,total:int,page:int,per_page:int,last_page:int,from:int,to:int} */
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(200, $perPage));
        $total   = $this->count();
        $rows    = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();
        $from    = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;

        return [
            'data'      => $rows,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from'      => $from,
            'to'        => min($total, $page * $perPage),
        ];
    }
}
