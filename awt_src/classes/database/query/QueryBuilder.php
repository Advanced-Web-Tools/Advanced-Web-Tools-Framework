<?php

namespace database\query;

/**
 * QueryBuilder
 *
 * Solely responsible for assembling SQL strings and their PDO bindings.
 * It never touches a database connection - execution is the provider's concern.
 *
 * Usage (mirrors the old DatabaseManager API):
 *
 *   $payload = (new QueryBuilder())
 *       ->table('users')
 *       ->select(['id', 'name'])
 *       ->where(['name' => 'Alice'])
 *       ->buildSelect();
 *
 *   // $payload->sql      → "SELECT id, name FROM users WHERE name = :name"
 *   // $payload->bindings → [':name' => 'Alice']
 *   // $payload->conditions → ['name' => 'Alice']   (used for cache invalidation)
 */
class QueryBuilder
{
    private string $table        = '';
    private string $selectClause = '';
    private array  $joins        = [];
    private string $whereClause  = '';
    private array  $bindings     = [];   // PDO placeholder → value (WHERE / LIMIT bindings)
    private array  $conditions   = [];   // raw column → value (for cache)
    private array  $orderByClauses = [];
    private array  $insertColumns   = [];
    private array  $insertBindings  = [];

    // -------------------------------------------------------------------------
    // Fluent setters
    // -------------------------------------------------------------------------

    public function table(string $name): self
    {
        $this->table = $name;
        return $this;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /** Prepare column / binding lists for a future INSERT. */
    public function insert(array $data): self
    {
        foreach ($data as $column => $value) {
            $this->insertColumns[]             = $column;
            $this->insertBindings[":{$column}"] = $value;
        }
        return $this;
    }

    /** Set the SELECT … FROM clause. */
    public function select(array $columns = ['*']): self
    {
        $this->selectClause = 'SELECT ' . implode(', ', $columns) . " FROM {$this->table}";
        return $this;
    }

    public function join(string $table, string $on, string $type = 'INNER'): self
    {
        $this->joins[] = " {$type} JOIN {$table} ON {$on}";
        return $this;
    }

    /**
     * Add a WHERE … = … (or != …) clause.
     *
     * Every column/value pair is also recorded in $this->conditions so that
     * the cache layer can later invalidate by matching WHERE criteria.
     */
    public function where(array $conditions, bool $useNot = false, string $conjunction = 'AND'): self
    {
        $conjunction = $this->sanitiseConjunction($conjunction);
        $operator    = $useNot ? '!=' : '=';

        $clauses = [];
        foreach ($conditions as $column => $value) {
            $placeholder    = ":{$column}";
            $clauses[]      = "{$column} {$operator} {$placeholder}";
            $this->bindings[$placeholder]  = $value;
            $this->conditions[$column]     = $value;
        }

        $this->whereClause = ' WHERE ' . implode(" {$conjunction} ", $clauses);
        return $this;
    }

    /** Add a WHERE … LIKE … clause. */
    public function like(array $conditions, bool $useNot = false): self
    {
        $operator = $useNot ? 'NOT LIKE' : 'LIKE';

        $clauses = [];
        foreach ($conditions as $column => $value) {
            $placeholder    = ":{$column}";
            $clauses[]      = "{$column} {$operator} {$placeholder}";
            $this->bindings[$placeholder]  = $value;
            $this->conditions[$column]     = $value;
        }

        $this->whereClause = ' WHERE ' . implode(' AND ', $clauses);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction              = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderByClauses[] = "{$column} {$direction}";
        return $this;
    }

    public function buildSelect(?int $limit = null, ?int $offset = null): QueryPayload
    {
        $sql      = $this->selectClause . implode('', $this->joins) . $this->whereClause;
        $bindings = $this->bindings;

        if (!empty($this->orderByClauses)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderByClauses);
        }

        if ($limit !== null) {
            $sql             .= ' LIMIT :limit';
            $bindings[':limit'] = $limit;

            if ($offset !== null) {
                $sql               .= ' OFFSET :offset';
                $bindings[':offset'] = $offset;
            }
        }

        return new QueryPayload($sql, $bindings, $this->table, $this->conditions);
    }

    public function buildInsert(): QueryPayload
    {
        if (count($this->insertColumns) !== count($this->insertBindings)) {
            throw new \InvalidArgumentException('Column count does not match value count.');
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $this->insertColumns),
            implode(', ', array_keys($this->insertBindings))
        );

        return new QueryPayload($sql, $this->insertBindings, $this->table, []);
    }

    /**
     * Build an UPDATE query.
     *
     * SET bindings use the prefix "set_" to avoid placeholder collisions when
     * the same column appears in both SET and WHERE (e.g. UPDATE t SET name=?
     * WHERE name=?).
     */
    public function buildUpdate(array $data): QueryPayload
    {
        $setClauses  = [];
        $setBindings = [];

        foreach ($data as $column => $value) {
            if ($value === 'DEFAULT') {
                $setClauses[] = "{$column} = DEFAULT";
            } else {
                $placeholder            = ":set_{$column}";
                $setClauses[]           = "{$column} = {$placeholder}";
                $setBindings[$placeholder] = $value;
            }
        }

        $sql      = "UPDATE {$this->table} SET " . implode(', ', $setClauses) . $this->whereClause;
        $bindings = array_merge($setBindings, $this->bindings);

        return new QueryPayload($sql, $bindings, $this->table, $this->conditions);
    }

    /** Build a DELETE query — refuses to build without a WHERE clause. */
    public function buildDelete(): QueryPayload
    {
        if ($this->whereClause === '') {
            throw new \LogicException(
                'DELETE without a WHERE clause is not allowed. ' .
                "Use ->where(['1' => '1']) if a full-table delete is truly intended."
            );
        }

        $sql = "DELETE FROM {$this->table}" . $this->whereClause;
        return new QueryPayload($sql, $this->bindings, $this->table, $this->conditions);
    }


    public function reset(): void
    {
        $this->table           = '';
        $this->selectClause    = '';
        $this->joins           = [];
        $this->whereClause     = '';
        $this->bindings        = [];
        $this->conditions      = [];
        $this->orderByClauses  = [];
        $this->insertColumns   = [];
        $this->insertBindings  = [];
    }


    private function sanitiseConjunction(string $conjunction): string
    {
        $upper = strtoupper($conjunction);
        return in_array($upper, ['AND', 'OR'], true) ? $upper : 'AND';
    }
}
