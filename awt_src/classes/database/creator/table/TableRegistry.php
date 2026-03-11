<?php

namespace database\creator\table;

use database\creator\interface\ITableRegistry;
use database\DatabaseManager;

/**
 * TableRegistry
 *
 * Manages the framework's schema registry: the awt_table and
 * awt_table_structure tables that record every table and column created
 * by the framework.
 *
 * All reads and writes go through DatabaseManager so they participate in
 * the same read-through cache and condition-aware invalidation as the rest
 * of the application.
 *
 * This class handles only registry data. It never issues DDL or touches
 * the real database schema; that is TableSchemaProvider's responsibility.
 */
class TableRegistry implements ITableRegistry
{
    /**
     * @param DatabaseManager $db The database manager used for all registry queries.
     */
    public function __construct(
        private readonly DatabaseManager $db,
    ) {}

    // =========================================================================
    // ITableRegistry
    // =========================================================================

    /** {@inheritdoc} */
    public function tableExists(string $name): bool
    {
        $rows = $this->db
            ->table('awt_table')
            ->select(['id'])
            ->where(['name' => $name])
            ->get();

        return !empty($rows);
    }

    /** {@inheritdoc} */
    public function columnExists(string $table, string $column): bool
    {
        $tableId = $this->getTableId($table);

        if ($tableId === null) {
            return false;
        }

        $rows = $this->db
            ->table('awt_table_structure')
            ->select(['id'])
            ->where(['table_id' => $tableId, 'column_name' => $column])
            ->get();

        return !empty($rows);
    }

    /** {@inheritdoc} */
    public function getTableId(string $name): ?int
    {
        $rows = $this->db
            ->table('awt_table')
            ->select(['id'])
            ->where(['name' => $name])
            ->get();

        return !empty($rows) ? (int) $rows[0]['id'] : null;
    }

    /** {@inheritdoc} */
    public function registerTable(string $name, int $creatorId): int
    {
        return (int) $this->db
            ->table('awt_table')
            ->insert(['name' => $name, 'creator' => $creatorId])
            ->executeInsert();
    }

    /** {@inheritdoc} */
    public function registerColumn(int $tableId, string $columnName, string $columnType): void
    {
        $this->db
            ->table('awt_table_structure')
            ->insert([
                'table_id'    => $tableId,
                'column_name' => $columnName,
                'column_type' => strtolower($columnType),
            ])
            ->executeInsert();
    }

    /** {@inheritdoc} */
    public function unregisterTable(string $name): void
    {
        $tableId = $this->getTableId($name);

        if ($tableId === null) {
            return;
        }

        // Remove all column entries first to satisfy any foreign key constraints
        // on awt_table_structure that reference awt_table.id.
        $this->db
            ->table('awt_table_structure')
            ->where(['table_id' => $tableId])
            ->delete();

        $this->db
            ->table('awt_table')
            ->where(['id' => $tableId])
            ->delete();
    }

    /** {@inheritdoc} */
    public function unregisterColumn(string $table, string $column): void
    {
        $tableId = $this->getTableId($table);

        if ($tableId === null) {
            return;
        }

        $this->db
            ->table('awt_table_structure')
            ->where(['table_id' => $tableId, 'column_name' => $column])
            ->delete();
    }

    /** {@inheritdoc} */
    public function updateColumnType(string $table, string $column, string $newType): void
    {
        $tableId = $this->getTableId($table);

        if ($tableId === null) {
            return;
        }

        $this->db
            ->table('awt_table_structure')
            ->where(['table_id' => $tableId, 'column_name' => $column])
            ->update(['column_type' => strtolower($newType)]);
    }

    /** {@inheritdoc} */
    public function renameColumn(string $table, string $oldName, string $newName): void
    {
        $tableId = $this->getTableId($table);

        if ($tableId === null) {
            return;
        }

        $this->db
            ->table('awt_table_structure')
            ->where(['table_id' => $tableId, 'column_name' => $oldName])
            ->update(['column_name' => $newName]);
    }
}
