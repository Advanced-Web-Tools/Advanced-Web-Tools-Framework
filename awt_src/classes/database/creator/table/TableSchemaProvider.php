<?php

namespace database\creator\table;

use database\creator\interface\ITableSchemaProvider;
use database\interface\IProvider;

/**
 * TableSchemaProvider
 *
 * Executes DDL statements (CREATE TABLE, ALTER TABLE, DROP TABLE, CREATE INDEX,
 * DROP INDEX) against the database by delegating to IProvider.
 *
 * DDL statements are passed as complete SQL strings with no PDO bindings.
 * IProvider::execute() accepts an empty bindings array for this purpose.
 *
 * This class never constructs SQL itself; that is TableWizard's responsibility.
 * It only receives a finished SQL string and runs it.
 */
class TableSchemaProvider implements ITableSchemaProvider
{
    /**
     * @param IProvider $provider The database execution provider.
     */
    public function __construct(
        private readonly IProvider $provider,
    ) {}

    /**
     * {@inheritdoc}
     *
     * The statement is executed with an empty bindings array because DDL
     * never uses parameterised placeholders.
     */
    public function executeDDL(string $sql): bool
    {
        $stmt = $this->provider->execute($sql, []);
        $stmt->closeCursor();
        return true;
    }
}
