<?php

namespace database;

use PDO;

/** Resolve SQL syntax from configuration, or the connection staged by the installer. */
final class DatabaseDialect
{
    public static function current(): string
    {
        global $shared;
        if (($shared['DBEngine']['PDO'] ?? null) instanceof PDO) {
            return $shared['DBEngine']['PDO']->getAttribute(PDO::ATTR_DRIVER_NAME);
        }

        return strtolower(defined('DB_TYPE') && DB_TYPE !== '' ? DB_TYPE : 'mysql');
    }
}
