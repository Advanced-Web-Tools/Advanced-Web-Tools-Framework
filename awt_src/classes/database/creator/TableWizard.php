<?php

namespace database\creator;

use database\cache\DatabaseCache;
use database\creator\column\ColumnSQLBuilder;
use database\creator\interface\ITableRegistry;
use database\creator\interface\ITableSchemaProvider;
use database\creator\table\TableRegistry;
use database\creator\table\TableSchemaProvider;
use database\DatabaseManager;
use database\provider\DatabaseProvider;
use database\query\QueryBuilder;

/**
 * TableWizard
 *
 * Facade for schema management. Exposes a high-level API for creating,
 * modifying, and dropping tables and columns while keeping all collaborators
 * separated by responsibility:
 *
 *   ITableSchemaProvider  Executes DDL statements against the database.
 *   ITableRegistry        Reads and writes the framework schema registry.
 *   ColumnSQLBuilder      Generates SQL fragments from ColumnDefinition objects.
 *
 * TableWizard no longer extends DatabaseManager. It receives a DatabaseManager
 * instance as a dependency and uses it only through the registry, never
 * accessing PDO directly.
 *
 * Full operation list:
 *   createTable()         Create a new table with all queued columns.
 *   dropTable()           Drop a table and remove it from the registry.
 *   addColumn()           Queue a column for the next createTable() call.
 *   addColumnToTable()    Add a single column to an existing table.
 *   dropColumn()          Remove a column from an existing table.
 *   modifyColumn()        Change the definition of an existing column.
 *   renameColumn()        Rename a column (MySQL 8+ / MariaDB 10.5+).
 *   renameTable()         Rename a table and update the registry.
 *   addIndex()            Add a named index to a column.
 *   dropIndex()           Drop a named index.
 *   tableExists()         Check whether a table is registered.
 *   columnExists()        Check whether a column is registered.
 */
class TableWizard
{
    /** Columns queued for the next createTable() call. */
    private array $columns = [];

    private ColumnSQLBuilder $sqlBuilder;

    /**
     * @param int                  $creatorId  The package/plugin ID recorded against new tables.
     * @param ITableSchemaProvider $schema     Executes DDL against the database.
     * @param ITableRegistry       $registry   Reads and writes the framework schema registry.
     */
    public function __construct(
        private readonly int                  $creatorId,
        private readonly ITableSchemaProvider $schema = new TableSchemaProvider(new DatabaseProvider()),
        private readonly ITableRegistry       $registry = new TableRegistry(new DatabaseManager()),
    ) {
        $this->sqlBuilder = new ColumnSQLBuilder();
    }

    /**
     * Convenience static constructor that wires the default concrete
     * collaborators automatically.
     *
     * @param int $creatorId The package/plugin ID for the new tables.
     */
    public static function create(int $creatorId): self
    {
        $provider = new DatabaseProvider();
        $db       = new DatabaseManager($provider, new QueryBuilder(), new DatabaseCache());

        return new self(
            creatorId: $creatorId,
            schema:    new TableSchemaProvider($provider),
            registry:  new TableRegistry($db),
        );
    }

    // =========================================================================
    // Column queue
    // =========================================================================

    /**
     * Queue a column to be included in the next createTable() call.
     * Has no effect on existing tables; use addColumnToTable() for that.
     *
     * @param ColumnCreator $column The column definition to queue.
     */
    public function addColumn(ColumnCreator $column): self
    {
        $this->columns[] = $column;
        return $this;
    }

    // =========================================================================
    // Table operations
    // =========================================================================

    /**
     * Create a new database table from the columns queued via addColumn().
     *
     * The table is built with ENGINE=InnoDB and utf8mb4_general_ci collation.
     * Foreign key constraints are appended as trailing clauses inside the
     * CREATE TABLE statement, separate from the column definitions.
     * Regular (non-unique) INDEX columns are created as standalone statements
     * after the table itself has been created.
     *
     * On success, the table and all its columns are recorded in the registry.
     * The column queue is cleared after each call regardless of outcome.
     *
     * @param string $tableName The name of the table to create.
     *
     * @return bool False if the table is already registered; true on success.
     */
    public function createTable(string $tableName): bool
    {
        if ($this->registry->tableExists($tableName)) {
            return false;
        }

        $columnClauses     = [];
        $foreignKeyClauses = [];

        foreach ($this->columns as $column) {
            $def                = $column->build();
            $columnClauses[]    = $this->sqlBuilder->buildColumnSQL($def);

            $fk = $this->sqlBuilder->buildForeignKeySQL($def);
            if ($fk !== null) {
                $foreignKeyClauses[] = $fk;
            }
        }

        $allClauses = implode(",\n    ", array_merge($columnClauses, $foreignKeyClauses));
        $sql        = "CREATE TABLE `{$tableName}` (\n    {$allClauses}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

        $result = $this->schema->executeDDL($sql);

        if ($result) {
            $tableId = $this->registry->registerTable($tableName, $this->creatorId);

            foreach ($this->columns as $column) {
                $def = $column->build();
                $this->registry->registerColumn($tableId, $def->name, $def->type);

                // Standalone index statements are run after the table exists.
                $indexSQL = $this->sqlBuilder->buildIndexSQL($tableName, $def);
                if ($indexSQL !== null) {
                    $this->schema->executeDDL($indexSQL);
                }
            }
        }

        $this->columns = [];
        return $result;
    }

    /**
     * Drop a table from the database and remove all of its entries from the
     * framework schema registry.
     *
     * @param string $tableName The table to drop.
     *
     * @return bool False if the table is not registered; true on success.
     */
    public function dropTable(string $tableName): bool
    {
        if (!$this->registry->tableExists($tableName)) {
            return false;
        }

        $result = $this->schema->executeDDL("DROP TABLE `{$tableName}`");

        if ($result) {
            $this->registry->unregisterTable($tableName);
        }

        return $result;
    }

    /**
     * Rename an existing table and update its entry in the registry.
     *
     * @param string $from Current table name.
     * @param string $to   New table name.
     *
     * @return bool False if the source table is not registered; true on success.
     */
    public function renameTable(string $from, string $to): bool
    {
        if (!$this->registry->tableExists($from)) {
            return false;
        }

        $result = $this->schema->executeDDL("RENAME TABLE `{$from}` TO `{$to}`");

        if ($result) {
            // Re-register the table under the new name, preserving the creator ID.
            $tableId = $this->registry->getTableId($from);
            $this->registry->unregisterTable($from);
            // registerTable creates a new entry; we pass the original creator ID.
            $this->registry->registerTable($to, $this->creatorId);
        }

        return $result;
    }

    // =========================================================================
    // Column operations
    // =========================================================================

    /**
     * Add a new column to an existing table.
     *
     * When the column carries a foreign key, the ADD COLUMN and ADD FOREIGN KEY
     * statements are issued as two separate ALTER TABLE calls. Concatenating a
     * FOREIGN KEY clause directly onto ADD COLUMN is invalid SQL.
     *
     * When the column carries a regular INDEX, a standalone CREATE INDEX
     * statement is issued after the column is added.
     *
     * @param string        $tableName The table to alter.
     * @param ColumnCreator $column    The column to add.
     *
     * @return bool False if the column is already registered; true on success.
     */
    public function addColumnToTable(string $tableName, ColumnCreator $column): bool
    {
        if ($this->registry->columnExists($tableName, $column->getName())) {
            return false;
        }

        $def    = $column->build();
        $result = $this->schema->executeDDL(
            "ALTER TABLE `{$tableName}` ADD COLUMN " . $this->sqlBuilder->buildColumnSQL($def)
        );

        if (!$result) {
            return false;
        }

        // Foreign key must be a separate ALTER TABLE statement.
        $fkSQL = $this->sqlBuilder->buildForeignKeySQL($def);
        if ($fkSQL !== null) {
            $this->schema->executeDDL("ALTER TABLE `{$tableName}` ADD {$fkSQL}");
        }

        // Standalone index statement when the column is marked INDEX.
        $indexSQL = $this->sqlBuilder->buildIndexSQL($tableName, $def);
        if ($indexSQL !== null) {
            $this->schema->executeDDL($indexSQL);
        }

        $tableId = $this->registry->getTableId($tableName);
        if ($tableId !== null) {
            $this->registry->registerColumn($tableId, $def->name, $def->type);
        }

        return true;
    }

    /**
     * Remove a column from an existing table.
     *
     * @param string $tableName  The table to alter.
     * @param string $columnName The column to drop.
     *
     * @return bool False if the column is not registered; true on success.
     */
    public function dropColumn(string $tableName, string $columnName): bool
    {
        if (!$this->registry->columnExists($tableName, $columnName)) {
            return false;
        }

        $result = $this->schema->executeDDL(
            "ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`"
        );

        if ($result) {
            $this->registry->unregisterColumn($tableName, $columnName);
        }

        return $result;
    }

    /**
     * Change the definition of an existing column (type, length, nullability,
     * default, etc.) using MODIFY COLUMN.
     *
     * The column must already exist. To rename a column, use renameColumn().
     *
     * @param string        $tableName The table to alter.
     * @param ColumnCreator $column    The new column definition. The name must
     *                                 match the existing column.
     *
     * @return bool False if the column is not registered; true on success.
     */
    public function modifyColumn(string $tableName, ColumnCreator $column): bool
    {
        if (!$this->registry->columnExists($tableName, $column->getName())) {
            return false;
        }

        $def    = $column->build();
        $result = $this->schema->executeDDL(
            "ALTER TABLE `{$tableName}` MODIFY COLUMN " . $this->sqlBuilder->buildColumnSQL($def)
        );

        if ($result) {
            $this->registry->updateColumnType($tableName, $def->name, $def->type);
        }

        return $result;
    }

    /**
     * Rename a column using ALTER TABLE RENAME COLUMN.
     *
     * Requires MySQL 8.0+ or MariaDB 10.5.2+. On older engines, use
     * modifyColumn() with the new name and full type definition instead.
     *
     * @param string $tableName The table to alter.
     * @param string $from      The current column name.
     * @param string $to        The new column name.
     *
     * @return bool False if the source column is not registered; true on success.
     */
    public function renameColumn(string $tableName, string $from, string $to): bool
    {
        if (!$this->registry->columnExists($tableName, $from)) {
            return false;
        }

        $result = $this->schema->executeDDL(
            "ALTER TABLE `{$tableName}` RENAME COLUMN `{$from}` TO `{$to}`"
        );

        if ($result) {
            $this->registry->renameColumn($tableName, $from, $to);
        }

        return $result;
    }

    // =========================================================================
    // Index operations
    // =========================================================================

    /**
     * Add a named index to a column on an existing table.
     *
     * @param string $tableName  The table to add the index to.
     * @param string $columnName The column to index.
     * @param string $indexType  INDEX or UNIQUE. Defaults to INDEX.
     * @param string $indexName  Optional custom index name. When empty, a name
     *                           is generated as idx_{table}_{column}.
     *
     * @return bool True on success.
     */
    public function addIndex(
        string $tableName,
        string $columnName,
        string $indexType = 'INDEX',
        string $indexName = '',
    ): bool {
        $indexType = strtoupper($indexType);
        $name      = $indexName !== '' ? $indexName : "idx_{$tableName}_{$columnName}";

        $sql = match ($indexType) {
            'UNIQUE' => "CREATE UNIQUE INDEX `{$name}` ON `{$tableName}` (`{$columnName}`)",
            default  => "CREATE INDEX `{$name}` ON `{$tableName}` (`{$columnName}`)",
        };

        return $this->schema->executeDDL($sql);
    }

    /**
     * Drop a named index from a table.
     *
     * @param string $tableName The table that owns the index.
     * @param string $indexName The index name to drop.
     *
     * @return bool True on success.
     */
    public function dropIndex(string $tableName, string $indexName): bool
    {
        return $this->schema->executeDDL(
            "DROP INDEX `{$indexName}` ON `{$tableName}`"
        );
    }

    // =========================================================================
    // Registry inspection
    // =========================================================================

    /**
     * Check whether a table is registered in the framework schema.
     *
     * @param string $name The table name to check.
     */
    public function tableExists(string $name): bool
    {
        return $this->registry->tableExists($name);
    }

    /**
     * Check whether a column is registered for a specific table.
     *
     * @param string $table  The table to inspect.
     * @param string $column The column name to check.
     */
    public function columnExists(string $table, string $column): bool
    {
        return $this->registry->columnExists($table, $column);
    }
}
