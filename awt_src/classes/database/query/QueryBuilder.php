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
    private int $parameterIndex = 0;
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
        $this->assertIdentifier($name);
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
            $this->assertIdentifier((string) $column, false);
            $this->insertColumns[] = $column;
            $this->insertBindings[$this->placeholder()] = $value;
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
        $this->assertIdentifier($table);
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT', 'LEFT OUTER', 'RIGHT OUTER', 'CROSS'], true)) {
            throw new \InvalidArgumentException('Unsupported JOIN type.');
        }
        // Legacy ON expressions are trusted SQL. Use joinOn() with external identifiers.
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
        // Preserve legacy replacement semantics, but discard replaced bindings.
        $this->bindings = [];
        $this->conditions = [];
        $this->whereClause = $conditions === [] ? '' : ' WHERE ' . $this->predicate($conditions, $useNot ? '!=' : '=', $conjunction);
        return $this;
    }

    public function andWhere(array $conditions, bool $useNot = false): self
    {
        return $this->appendWhere($conditions, $useNot, 'AND');
    }

    public function orWhere(array $conditions, bool $useNot = false): self
    {
        return $this->appendWhere($conditions, $useNot, 'OR');
    }

    private function appendWhere(array $conditions, bool $useNot, string $conjunction): self
    {
        if ($conditions === []) return $this;
        $predicate = $this->predicate($conditions, $useNot ? '!=' : '=', 'AND');
        $this->whereClause = $this->whereClause === '' ? ' WHERE ' . $predicate
            : ' WHERE (' . substr($this->whereClause, 7) . ') ' . $conjunction . ' (' . $predicate . ')';
        return $this;
    }

    private function predicate(array $conditions, string $operator, string $conjunction): string
    {
        $clauses = [];
        foreach ($conditions as $column => $value) {
            $this->assertIdentifier((string) $column, true, true);
            $this->conditions[$column] = $value;
            if ($value === null && in_array($operator, ['=', '!='], true)) {
                $clauses[] = $column . ($operator === '=' ? ' IS NULL' : ' IS NOT NULL');
            } else {
                $placeholder = $this->placeholder();
                $clauses[] = "{$column} {$operator} {$placeholder}";
                $this->bindings[$placeholder] = $value;
            }
        }
        return implode(' ' . $this->sanitiseConjunction($conjunction) . ' ', $clauses);
    }

    private function placeholder(): string { return ':p' . ++$this->parameterIndex; }

    private function assertIdentifier(string $identifier, bool $qualified = true, bool $numeric = false): void
    {
        if ($numeric && preg_match('/^[0-9]+$/D', $identifier)) return;
        $part = '(?:[A-Za-z_][A-Za-z0-9_]*|`[A-Za-z_][A-Za-z0-9_]*`)';
        $pattern = $qualified ? '/^' . $part . '(?:\\.' . $part . ')*$/D' : '/^' . $part . '$/D';
        if (!preg_match($pattern, $identifier)) throw new \InvalidArgumentException("Invalid SQL identifier: {$identifier}");
    }

    public function joinOn(string $table, string $leftColumn, string $rightColumn, string $type = 'INNER'): self
    {
        $this->assertIdentifier($leftColumn);
        $this->assertIdentifier($rightColumn);
        return $this->join($table, "{$leftColumn} = {$rightColumn}", $type);
    }

    /** Add a WHERE … LIKE … clause. */
    public function like(array $conditions, bool $useNot = false): self
    {
        $this->bindings = [];
        $this->conditions = [];
        $this->whereClause = $conditions === [] ? '' : ' WHERE ' . $this->predicate($conditions, $useNot ? 'NOT LIKE' : 'LIKE', 'AND');
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->assertIdentifier($column);
        $direction              = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderByClauses[] = "{$column} {$direction}";
        return $this;
    }

    public function buildSelect(?int $limit = null, ?int $offset = null): QueryPayload
    {
        if (($limit !== null && $limit < 0) || ($offset !== null && $offset < 0)) {
            throw new \InvalidArgumentException('Pagination values must be nonnegative.');
        }
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
        if ($this->insertColumns === []) throw new \InvalidArgumentException('Cannot insert an empty attribute list.');
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
        if ($data === []) throw new \InvalidArgumentException('Cannot update an empty attribute list.');
        $setClauses  = [];
        $setBindings = [];

        foreach ($data as $column => $value) {
            $this->assertIdentifier((string) $column, false);
            if ($value instanceof SqlExpression || $value === 'DEFAULT') {
                $expression = $value instanceof SqlExpression ? $value->sql : 'DEFAULT';
                $setClauses[] = "{$column} = {$expression}";
            } else {
                $placeholder = $this->placeholder();
                $setClauses[]           = "{$column} = {$placeholder}";
                $setBindings[$placeholder] = $value instanceof BoundValue ? $value->value : $value;
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
        $this->parameterIndex = 0;
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
