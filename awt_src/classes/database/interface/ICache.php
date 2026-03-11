<?php

namespace database\interface;

/**
 * Contract for the query result cache.
 *
 * The cache is keyed by (table, SQL string).  Fine-grained invalidation is
 * driven by the raw WHERE conditions that were active when the query was
 * cached, enabling column-value–level eviction (e.g. "invalidate everything
 * cached for users WHERE name = 'Alice'").
 */
interface ICache
{
    /**
     * Return cached rows for the given query, or false on a cache miss.
     *
     * @param string $table The primary table the query targets.
     * @param string $query The full SQL string used as the cache key.
     *
     * @return array|false Cached rows on hit; false on miss.
     */
    public function get(string $table, string $query): array|false;

    /**
     * Store query results together with the WHERE conditions that produced them.
     *
     * @param string $table      The primary table.
     * @param string $query      The full SQL string (cache key).
     * @param array  $result     The rows to cache.
     * @param array  $conditions Raw column → value WHERE pairs (used for
     *                           targeted invalidation later).
     */
    public function set(string $table, string $query, array $result, array $conditions): void;

    /**
     * Invalidate cached queries that overlap with the given CUD conditions.
     *
     * Rules:
     *  - Queries cached without any WHERE conditions (full-table scans) are
     *    always invalidated on any CUD touching the same table.
     *  - Queries cached WITH conditions are invalidated when at least one
     *    column+value pair from $cudConditions matches their stored conditions.
     *  - When $cudConditions is empty (bulk / no-WHERE CUD), ALL cached
     *    queries for the table are invalidated.
     *
     * @param string $table         The table being mutated.
     * @param array  $cudConditions Raw column → value pairs from the CUD WHERE clause.
     */
    public function invalidate(string $table, array $cudConditions): void;

    /**
     * Unconditionally drop every cached entry for a table.
     */
    public function invalidateTable(string $table): void;
}
