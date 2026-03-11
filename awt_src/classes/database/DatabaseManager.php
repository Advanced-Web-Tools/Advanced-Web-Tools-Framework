<?php

namespace database;

use database\cache\DatabaseCache;
use database\interface\ICache;
use database\interface\IProvider;
use database\provider\DatabaseProvider;
use database\query\QueryBuilder;
use database\trait\DoNotCache;
use PDO;

/**
 * DatabaseManager
 *
 * Facade that exposes a fluent, chainable API for all database operations.
 * Every concern is delegated to a dedicated collaborator:
 *
 *   QueryBuilder  Assembles parameterised SQL strings and their PDO bindings.
 *   IProvider     Executes prepared statements against the database connection.
 *   ICache        Stores SELECT results and evicts them when data changes.
 *
 * Cache behaviour is determined at construction time by inspecting whether the
 * DoNotCache trait is present on the concrete class. When it is, all cache
 * reads and writes are skipped for the lifetime of the instance; the database
 * is always queried directly.
 *
 * All dependencies have default values and can be replaced via constructor
 * injection, making the class straightforward to test in isolation.
 */
class DatabaseManager
{
    /** @var array Holds the result of the last getTables() call for schema inspection. */
    public array $tables = [];

    /** @var string The SQL string produced by the most recently executed operation. */
    private string $lastQuery = '';

    /**
     * @var bool Controls whether the cache is consulted on reads and populated
     *           on misses. Set to false when the DoNotCache trait is detected
     *           on the current class or any of its parents.
     */
    private bool $cacheOn = true;

    /**
     * @param IProvider    $provider Executes SQL against the database.
     * @param QueryBuilder $builder  Assembles SQL strings and bindings from fluent calls.
     * @param ICache       $cache    Stores and invalidates cached query results.
     */
    public function __construct(
        private readonly IProvider    $provider = new DatabaseProvider(),
        private readonly QueryBuilder $builder  = new QueryBuilder(),
        private readonly ICache       $cache    = new DatabaseCache(),
    ) {
        $this->cacheOn = !in_array(DoNotCache::class, self::class_uses_recursive($this));
    }


    /**
     * Set the table all subsequent query clauses will target.
     *
     * @param string $name Table name.
     */
    public function table(string $name): self
    {
        $this->builder->table($name);
        return $this;
    }

    /**
     * Prepare column/value pairs for an INSERT statement.
     * Must be followed by executeInsert() to run the query.
     *
     * @param array $data Associative array of column => value pairs to insert.
     */
    public function insert(array $data): self
    {
        $this->builder->insert($data);
        return $this;
    }

    /**
     * Set the column list for a SELECT statement.
     * Defaults to wildcard (*) when no columns are provided.
     *
     * @param array $columns List of column names or expressions to select.
     */
    public function select(array $columns = ['*']): self
    {
        $this->builder->select($columns);
        return $this;
    }

    /**
     * Append a JOIN clause to the current SELECT query.
     * Multiple calls append multiple joins in the order they are called.
     *
     * @param string $table The table to join.
     * @param string $on    The ON condition (e.g. "posts.user_id = users.id").
     * @param string $type  Join type: INNER, LEFT, RIGHT, etc. Defaults to INNER.
     */
    public function join(string $table, string $on, string $type = 'INNER'): self
    {
        $this->builder->join($table, $on, $type);
        return $this;
    }

    /**
     * Add a WHERE clause using equality (or inequality) conditions.
     *
     * All column/value pairs are recorded and later forwarded to the cache
     * layer so that CUD operations can evict only the cache entries whose
     * conditions overlap with the mutation.
     *
     * @param array  $conditions  Associative array of column => value pairs.
     * @param bool   $useNot      When true, uses != instead of = for all conditions.
     * @param string $conjunction Logical operator joining multiple conditions: AND or OR.
     */
    public function where(array $conditions, bool $useNot = false, string $conjunction = 'AND'): self
    {
        $this->builder->where($conditions, $useNot, $conjunction);
        return $this;
    }

    /**
     * Add a WHERE clause using LIKE pattern matching.
     *
     * @param array $conditions Associative array of column => pattern pairs.
     * @param bool  $useNot     When true, uses NOT LIKE instead of LIKE.
     */
    public function like(array $conditions, bool $useNot = false): self
    {
        $this->builder->like($conditions, $useNot);
        return $this;
    }

    /**
     * Append an ORDER BY expression to the SELECT query.
     * Multiple calls append multiple sort expressions in the order called.
     *
     * @param string $column    The column to sort by.
     * @param string $direction ASC or DESC. Defaults to ASC.
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->builder->orderBy($column, $direction);
        return $this;
    }

    /**
     * Execute the INSERT prepared by insert() and return the new row's ID.
     *
     * After a successful insert, all cached queries for the target table are
     * invalidated. Because an INSERT carries no WHERE conditions, there is no
     * basis for a narrower eviction; the entire table cache is flushed.
     *
     * @return int|null The auto-increment ID of the inserted row, or null if
     *                  no ID was generated.
     */
    public function executeInsert(): ?int
    {
        $payload = $this->builder->buildInsert();
        $this->builder->reset();

        $stmt = $this->provider->execute($payload->sql, $payload->bindings);
        $stmt->closeCursor();

        $id = $this->provider->lastInsertId();

        $this->cache->invalidate($payload->table, []);

        $this->lastQuery = $payload->sql;
        self::showDebugTrace();

        return $id ?: null;
    }

    /**
     * Execute the SELECT prepared by select(), where(), join(), and orderBy(),
     * then return all matching rows as an associative array.
     *
     * Read path when caching is active:
     *   1. Check the cache. On a hit, return the stored rows immediately.
     *   2. On a miss, execute the query against the database.
     *   3. Store the result in the cache, indexed by the SQL string and the
     *      raw WHERE conditions, then return the rows.
     *
     * When the DoNotCache trait is present, steps 1 and 3 are skipped and the
     * database is always queried directly.
     *
     * @param int|null $limit  Maximum number of rows to return.
     * @param int|null $offset Number of rows to skip before returning results.
     *
     * @return array Rows as associative arrays, keyed by column name.
     */
    public function get(?int $limit = null, ?int $offset = null): array
    {
        $payload = $this->builder->buildSelect($limit, $offset);
        $this->builder->reset();

        $this->lastQuery = $payload->sql;
        self::showDebugTrace();

        if ($this->cacheOn) {
            $cached = $this->cache->get($payload->table, $payload->sql);
            if ($cached !== false) {
                return $cached;
            }
        }

        $stmt   = $this->provider->execute($payload->sql, $payload->bindings);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($this->cacheOn)
            $this->cache->set($payload->table, $payload->sql, $result, $payload->conditions);

        return $result;
    }

    /**
     * Execute the UPDATE prepared by where() and return whether any rows changed.
     *
     * After execution, the cache is invalidated selectively: only cached SELECT
     * queries whose stored WHERE conditions overlap with the UPDATE's WHERE
     * conditions are evicted. Cached queries for the same table that target
     * entirely different rows are left intact.
     *
     * Full-table SELECT caches (those stored without any WHERE conditions) are
     * always evicted on any UPDATE, because they may contain the affected rows.
     *
     * @param array $data Associative array of column => new value pairs to set.
     *
     * @return bool True if at least one row was modified, false otherwise.
     */
    public function update(array $data): bool
    {
        $payload = $this->builder->buildUpdate($data);
        $this->builder->reset();

        $stmt   = $this->provider->execute($payload->sql, $payload->bindings);
        $result = $stmt->rowCount() > 0;
        $stmt->closeCursor();

        $this->cache->invalidate($payload->table, $payload->conditions);

        $this->lastQuery = $payload->sql;
        self::showDebugTrace();

        return $result;
    }

    /**
     * Execute the DELETE prepared by where() and return whether any rows were removed.
     *
     * Calling delete() without a preceding where() call throws a LogicException.
     * To delete all rows intentionally, use where(['1' => '1']) first.
     *
     * Cache invalidation follows the same overlap rules as update(): only cached
     * SELECT queries whose conditions intersect with the DELETE's WHERE conditions
     * are evicted.
     *
     * @return bool True if at least one row was deleted, false otherwise.
     *
     * @throws \LogicException When no WHERE clause has been set.
     */
    public function delete(): bool
    {
        $payload = $this->builder->buildDelete();
        $this->builder->reset();

        $stmt   = $this->provider->execute($payload->sql, $payload->bindings);
        $result = $stmt->rowCount() > 0;
        $stmt->closeCursor();

        $this->cache->invalidate($payload->table, $payload->conditions);

        $this->lastQuery = $payload->sql;
        self::showDebugTrace();

        return $result;
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    /**
     * Fetch all table and column definitions from the framework schema tables
     * and store them in $this->tables for use by checkTable() and checkColumn().
     *
     * Results go through the normal get() path and are cached like any other
     * SELECT query.
     */
    public function getTables(): self
    {
        $this->tables = $this->table('awt_table')
            ->select(['*'])
            ->where(['1' => 1])
            ->join('awt_table_structure', 'awt_table.id = awt_table_structure.table_id')
            ->get();

        return $this;
    }

    /**
     * Check whether a table with the given name exists in the schema.
     *
     * Calls getTables() automatically if the schema has not been loaded yet.
     *
     * @param string $table The table name to look for.
     *
     * @return bool True if the table exists, false otherwise.
     */
    public function checkTable(string $table): bool
    {
        if (empty($this->tables)) {
            $this->getTables();
        }

        foreach ($this->tables as $row) {
            if (isset($row['name']) && $row['name'] === $table) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a specific column exists within a specific table.
     *
     * Calls getTables() automatically if the schema has not been loaded yet.
     *
     * @param string $table  The table to inspect.
     * @param string $column The column name to look for within that table.
     *
     * @return bool True if the column exists in the table, false otherwise.
     */
    public function checkColumn(string $table, string $column): bool
    {
        if (empty($this->tables)) {
            $this->getTables();
        }

        foreach ($this->tables as $row) {
            if (
                isset($row['name'], $row['column_name']) &&
                $row['name'] === $table &&
                $row['column_name'] === $column
            ) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // Debug helpers
    // =========================================================================

    /**
     * Return the SQL string produced by the most recently executed operation.
     * Useful for logging and verifying query output during development.
     */
    public function getLastQuery(): string
    {
        return $this->lastQuery;
    }

    /**
     * Print a chain of file/line references showing where the current SQL call
     * originated. Output is suppressed unless both DEBUG and
     * SHOW_SQL_CONNECTIONS_CALLS constants are defined and truthy.
     */
    private static function showDebugTrace(): void
    {
        if (!defined('DEBUG') || !DEBUG) {
            return;
        }

        if (!defined('SHOW_SQL_CONNECTIONS_CALLS') || !SHOW_SQL_CONNECTIONS_CALLS) {
            return;
        }

        echo 'SQL called by: ' . self::getCallerChain() . '<br>';
    }

    /**
     * Build a human-readable call chain from the current backtrace.
     *
     * Frames are reversed so the outermost caller appears first, making it
     * easier to trace the origin of a query through layers of nested calls.
     *
     * @return string File:line pairs joined by " -> ", outermost caller first.
     */
    private static function getCallerChain(): string
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $parts  = [];

        foreach ($frames as $frame) {
            if (isset($frame['file'])) {
                $parts[] = basename($frame['file']) . ':' . ($frame['line'] ?? '?');
            }
        }

        return implode(' -> ', array_reverse($parts));
    }

    /**
     * Collect all traits used by a class and its full parent chain.
     *
     * PHP's built-in class_uses() only inspects the class it is directly given
     * and does not walk up the inheritance tree. This method merges the trait
     * lists for the given class and every parent class, ensuring that traits
     * declared anywhere in the hierarchy are detected.
     *
     * Used at construction time to determine whether the DoNotCache trait is
     * present on the concrete class being instantiated.
     *
     * @param object|string $class The object instance or fully-qualified class name to inspect.
     *
     * @return array Flat array of fully-qualified trait names found across the hierarchy.
     */
    private static function class_uses_recursive(object|string $class): array
    {
        $results = [];

        foreach (array_merge([$class], class_parents($class)) as $c) {
            $results += class_uses($c);
        }

        return array_unique($results);
    }
}