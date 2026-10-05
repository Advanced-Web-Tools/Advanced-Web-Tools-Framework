<?php

namespace database\interface;

use PDOStatement;

/**
 * Contract for a database execution provider.
 *
 * Decouples the rest of the system from the concrete PDO implementation,
 * making it easy to swap drivers or inject test doubles.
 */
interface IProvider
{
    /**
     * Prepare, bind, and execute a SQL statement.
     *
     * @param string $sql      Parameterised SQL string.
     * @param array  $bindings Placeholder → value map.
     *
     * @return PDOStatement The executed (open cursor) statement.
     */
    public function execute(string $sql, array $bindings = []): PDOStatement;

    /**
     * Return the auto-increment ID produced by the last INSERT.
     */
    public function lastInsertId(): int;
}
