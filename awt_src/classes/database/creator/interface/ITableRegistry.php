<?php

namespace database\creator\interface;

/**
 * ITableRegistry
 *
 * Contract for reading and writing the framework's schema registry tables
 * (awt_table and awt_table_structure).
 *
 * The registry is a parallel record of every table and column managed by the
 * framework. It exists alongside the real database schema so that the
 * framework can query structural information without relying on
 * INFORMATION_SCHEMA, which is not available in all configurations.
 *
 * All registry mutations are performed through DatabaseManager so they
 * benefit from the same caching and invalidation system as the rest of the
 * application.
 */
interface ITableRegistry
{
    /**
     * Check whether a table is registered in the framework schema.
     *
     * @param string $name The table name to look up.
     *
     * @return bool True if the table exists in the registry.
     */
    public function tableExists(string $name): bool;

    /**
     * Check whether a column is registered for a specific table.
     *
     * @param string $table  The table to inspect.
     * @param string $column The column name to look up.
     *
     * @return bool True if the column is registered under that table.
     */
    public function columnExists(string $table, string $column): bool;

    /**
     * Retrieve the internal registry ID for a table.
     *
     * @param string $name The table name.
     *
     * @return int|null The ID, or null if the table is not registered.
     */
    public function getTableId(string $name): ?int;

    /**
     * Add a table entry to the registry and return the assigned ID.
     *
     * @param string $name      The table name.
     * @param int    $creatorId The package/plugin ID that created the table.
     *
     * @return int The auto-increment ID assigned to the new registry entry.
     */
    public function registerTable(string $name, int $creatorId): int;

    /**
     * Register a column under an existing table registry entry.
     *
     * @param int    $tableId     The registry ID returned by registerTable().
     * @param string $columnName  The column name.
     * @param string $columnType  The lowercase SQL type keyword (e.g. "int", "varchar").
     */
    public function registerColumn(int $tableId, string $columnName, string $columnType): void;

    /**
     * Remove a table and all of its column entries from the registry.
     * Does not drop the real database table; that is the provider's concern.
     *
     * @param string $name The table name to remove.
     */
    public function unregisterTable(string $name): void;

    /**
     * Remove a single column entry from the registry.
     * Does not alter the real database table; that is the provider's concern.
     *
     * @param string $table  The table that owns the column.
     * @param string $column The column name to remove.
     */
    public function unregisterColumn(string $table, string $column): void;

    /**
     * Update the registered type of an existing column.
     * Called after MODIFY COLUMN to keep the registry in sync.
     *
     * @param string $table      The table that owns the column.
     * @param string $column     The column whose type changed.
     * @param string $newType    The new lowercase SQL type keyword.
     */
    public function updateColumnType(string $table, string $column, string $newType): void;

    /**
     * Rename a column entry in the registry.
     * Called after RENAME COLUMN to keep the registry in sync.
     *
     * @param string $table   The table that owns the column.
     * @param string $oldName The current column name.
     * @param string $newName The new column name.
     */
    public function renameColumn(string $table, string $oldName, string $newName): void;
}
