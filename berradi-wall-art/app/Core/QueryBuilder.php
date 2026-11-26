<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small, safe fluent query builder. Values are always bound; identifiers are validated.
 */
final class QueryBuilder
{
    private string $table;
    /** @var list<string> */
    private array $columns = ['*'];
    /** @var list<array{0:string,1:string}> boolean, sql */
    private array $wheres = [];
    /** @var array<string,mixed> */
    private array $bindings = [];
    /** @var list<string> */
    private array $orders = [];
    /** @var list<string> */
    private array $joins = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private int $paramIndex = 0;

    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    public function __construct(private Database $db, string $table)
    {
        $this->table = Database::ident($table);
    }

    /** @param string|list<string> $columns */
    public function select(string|array $columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $this->columns = array_map(function ($c) {
            if ($c === '*') {
                return '*';
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.\*$/', $c, $m)) {
                return Database::ident($m[1]) . '.*';
            }
            if (preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $c, $m)) {
                return Database::ident(trim($m[1])) . ' AS ' . Database::ident($m[2]);
            }
            return Database::ident($c);
        }, $columns);
        return $this;
    }

    /**
     * Raw select expression (developer-provided only, never user input).
     */
    public function selectRaw(string $expression): self
    {
        $this->columns = [$expression];
        return $this;
    }

    public function join(string $table, string $first, string $op, string $second, string $type = 'INNER'): self
    {
        if (!in_array($op, ['=', '<>', '!='], true)) {
            throw new \InvalidArgumentException('Invalid join operator');
        }
        $type = in_array(strtoupper($type), ['INNER', 'LEFT', 'RIGHT'], true) ? strtoupper($type) : 'INNER';
        $this->joins[] = "{$type} JOIN " . Database::ident($table) . ' ON ' . Database::ident($first) . " {$op} " . Database::ident($second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $op, string $second): self
    {
        return $this->join($table, $first, $op, $second, 'LEFT');
    }

    private function param(mixed $value): string
    {
        $name = 'qb' . (++$this->paramIndex);
        $this->bindings[$name] = $value;
        return ':' . $name;
    }

    public function where(string $column, mixed $operator, mixed $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $operator = strtoupper((string) $operator);
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException('Invalid operator ' . $operator);
        }
        if ($value === null) {
            return $operator === '=' ? $this->whereNull($column, $boolean) : $this->whereNotNull($column, $boolean);
        }
        $this->wheres[] = [$boolean, Database::ident($column) . " {$operator} " . $this->param($value)];
        return $this;
    }

    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->where($column, '=', $operator, 'OR');
        }
        return $this->where($column, $operator, $value, 'OR');
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        if ($values === []) {
            $this->wheres[] = [$boolean, $not ? '1 = 1' : '1 = 0'];
            return $this;
        }
        $ph = array_map(fn ($v) => $this->param($v), array_values($values));
        $this->wheres[] = [$boolean, Database::ident($column) . ($not ? ' NOT IN (' : ' IN (') . implode(', ', $ph) . ')'];
        return $this;
    }

    /** @param list<mixed> $values */
    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'AND', true);
    }

    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [$boolean, Database::ident($column) . ' IS NULL'];
        return $this;
    }

    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->wheres[] = [$boolean, Database::ident($column) . ' IS NOT NULL'];
        return $this;
    }

    public function whereBetween(string $column, mixed $from, mixed $to): self
    {
        $this->wheres[] = ['AND', Database::ident($column) . ' BETWEEN ' . $this->param($from) . ' AND ' . $this->param($to)];
        return $this;
    }

    /**
     * Search a term over several columns: (a LIKE %t% OR b LIKE %t%)
     *
     * @param list<string> $columns
     */
    public function search(array $columns, string $term): self
    {
        $term = trim($term);
        if ($term === '' || $columns === []) {
            return $this;
        }
        $like = '%' . addcslashes($term, '%_\\') . '%';
        $parts = array_map(fn ($c) => Database::ident($c) . ' LIKE ' . $this->param($like), $columns);
        $this->wheres[] = ['AND', '(' . implode(' OR ', $parts) . ')'];
        return $this;
    }

    /**
     * Raw where fragment with named bindings (developer-provided SQL only).
     *
     * @param array<string,mixed> $bindings
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        foreach ($bindings as $k => $v) {
            $this->bindings[ltrim((string) $k, ':')] = $v;
        }
        $this->wheres[] = [$boolean, '(' . $sql . ')'];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = Database::ident($column) . ' ' . $direction;
        return $this;
    }

    public function latest(string $column = 'id'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    private function compileWhere(): string
    {
        if ($this->wheres === []) {
            return '';
        }
        $sql = '';
        foreach ($this->wheres as $i => [$bool, $clause]) {
            $sql .= ($i === 0 ? ' WHERE ' : " {$bool} ") . $clause;
        }
        return $sql;
    }

    private function compileFrom(): string
    {
        return ' FROM ' . $this->table . ($this->joins ? ' ' . implode(' ', $this->joins) : '');
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . $this->compileFrom() . $this->compileWhere();
        if ($this->orders) {
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

    /** @return list<array<string,mixed>> */
    public function get(): array
    {
        return $this->db->fetchAll($this->toSql(), $this->bindings);
    }

    /** @return array<string,mixed>|null */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit = 1;
        return $this->db->fetch($clone->toSql(), $clone->bindings);
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*)' . $this->compileFrom() . $this->compileWhere();
        return (int) $this->db->value($sql, $this->bindings);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function sum(string $column): string
    {
        $sql = 'SELECT COALESCE(SUM(' . Database::ident($column) . '), 0)' . $this->compileFrom() . $this->compileWhere();
        return (string) $this->db->value($sql, $this->bindings);
    }

    /** @return list<mixed> */
    public function pluck(string $column): array
    {
        $clone = clone $this;
        $clone->select([$column]);
        return array_column($this->db->fetchAll($clone->toSql(), $clone->bindings), str_contains($column, '.') ? substr($column, strpos($column, '.') + 1) : $column);
    }

    public function paginate(int $perPage = 20, ?int $page = null): Paginator
    {
        $perPage = max(1, min(200, $perPage));
        $page = $page ?? max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->count();
        $clone = clone $this;
        $clone->limit = $perPage;
        $clone->offset = ($page - 1) * $perPage;
        return new Paginator($this->db->fetchAll($clone->toSql(), $clone->bindings), $total, $perPage, $page);
    }

    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new \LogicException('Refusing to DELETE without WHERE clause');
        }
        return $this->db->query('DELETE FROM ' . $this->table . $this->compileWhere(), $this->bindings)->rowCount();
    }

    /** @param array<string,mixed> $data */
    public function update(array $data): int
    {
        if ($this->wheres === []) {
            throw new \LogicException('Refusing to UPDATE without WHERE clause');
        }
        $set = [];
        foreach ($data as $col => $val) {
            $set[] = Database::ident($col) . ' = ' . $this->param($val);
        }
        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $set) . $this->compileWhere();
        return $this->db->query($sql, $this->bindings)->rowCount();
    }
}
