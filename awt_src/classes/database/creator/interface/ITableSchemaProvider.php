<?php

namespace database\creator\interface;

/**
 * ITableSchemaProvider
 *
 * Contract for executing DDL (Data Definition Language) statements against
 * the database. DDL covers CREATE TABLE, ALTER TABLE, DROP TABLE, and
 * related index operations.
 *
 * DDL statements are structurally different from DML (SELECT/INSERT/UPDATE/
 * DELETE): they carry no parameterised bindings and return no result rows.
 * This interface therefore does not reuse IProvider; it has its own minimal
 * surface suited to schema management.
 *
 * Separating DDL execution behind this interface means TableWizard never
 * touches a PDO connection directly and can be tested with a stub provider.
 */
interface ITableSchemaProvider
{
    /**
     * Execute a DDL statement and return whether it succeeded.
     *
     * @param string $sql A complete DDL statement with no placeholders.
     *
     * @return bool True on success.
     *
     * @throws \RuntimeException On execution failure when DEBUG is enabled.
     */
    public function executeDDL(string $sql): bool;
}
