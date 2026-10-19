<?php

namespace database\creator\column;

use database\DatabaseDialect;

/**
 * ColumnSQLBuilder
 *
 * Sole responsibility: generate SQL fragments from a ColumnDefinition.
 * Each instance retains its SQL dialect; methods transform the definitions
 * they receive without changing query state.
 *
 * Three kinds of SQL are produced:
 *
 *   buildColumnSQL()        The column definition clause used inside CREATE TABLE
 *                           or ALTER TABLE ADD COLUMN / MODIFY COLUMN.
 *
 *   buildForeignKeySQL()    The FOREIGN KEY … REFERENCES … constraint clause,
 *                           used as a separate statement in ALTER TABLE or as
 *                           a trailing clause inside CREATE TABLE.
 *
 *   buildIndexSQL()         A standalone CREATE INDEX statement for columns
 *                           whose index type is INDEX (non-primary, non-unique).
 *                           Primary and unique indexes are inlined by buildColumnSQL().
 */
class ColumnSQLBuilder
{
    private readonly string $dialect;

    public function __construct(?string $dialect = null)
    {
        $this->dialect = strtolower($dialect ?? DatabaseDialect::current());
    }

    /**
     * SQL keyword defaults that must not be wrapped in single quotes.
     * Comparisons are performed case-insensitively.
     */
    private const UNQUOTED_DEFAULTS = [
        'CURRENT_TIMESTAMP',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'NOW()',
        'NULL',
        'TRUE',
        'FALSE',
        '0',
        '1',
    ];

    /**
     * Generate the column definition SQL fragment.
     *
     * Covers: type, length, UNSIGNED, NULL/NOT NULL, DEFAULT, AUTO_INCREMENT,
     * inline PRIMARY KEY / UNIQUE, COMMENT, and AFTER positioning.
     *
     * For ENUM and SET types the $enumValues list on the definition is used
     * to build the parenthesised value list instead of $length.
     *
     * @param ColumnDefinition $def The column to generate SQL for.
     *
     * @return string SQL fragment suitable for use in CREATE TABLE or ALTER TABLE.
     */
    public function buildColumnSQL(ColumnDefinition $def): string
    {
        if ($this->dialect === 'sqlite') return $this->buildSQLiteColumnSQL($def);
        if ($this->dialect === 'pgsql') return $this->buildPostgreSQLColumnSQL($def);

        $sql = "`{$def->name}` {$def->type}";

        // ENUM and SET derive their parenthesised list from $enumValues.
        if (in_array($def->type, ['ENUM', 'SET'], true) && !empty($def->enumValues)) {
            $quoted = array_map(fn(string $v) => "'{$v}'", $def->enumValues);
            $sql   .= '(' . implode(', ', $quoted) . ')';
        } elseif ($def->length !== '') {
            $sql .= "({$def->length})";
        }

        if ($def->unsigned && $this->isNumericType($def->type)) {
            $sql .= ' UNSIGNED';
        }

        $sql .= $def->nullable ? ' NULL' : ' NOT NULL';

        if ($def->default !== '') {
            $sql .= $this->buildDefaultClause($def->default, $def->defaultAsDefined);
        }

        if ($def->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($def->index === 'PRIMARY') {
            $sql .= ' PRIMARY KEY';
        } elseif ($def->index === 'UNIQUE') {
            $sql .= ' UNIQUE';
        }

        if ($def->comment !== '') {
            $escaped = str_replace("'", "''", $def->comment);
            $sql    .= " COMMENT '{$escaped}'";
        }

        if ($def->after !== '') {
            $sql .= " AFTER `{$def->after}`";
        }

        return $sql;
    }

    private function buildSQLiteColumnSQL(ColumnDefinition $def): string
    {
        $type = strtoupper($def->type);
        if ($type === 'SET') {
            throw new \LogicException('SQLite does not support SET columns. Use TEXT instead.');
        }
        $integer = in_array($type, ['INT', 'INTEGER', 'TINYINT', 'SMALLINT', 'MEDIUMINT', 'BIGINT'], true);
        if ($def->autoIncrement && !$integer) {
            throw new \LogicException('SQLite auto-increment columns must be integer primary keys.');
        }
        $sqlType = match (true) {
            $integer => 'INTEGER',
            in_array($type, ['FLOAT', 'DOUBLE', 'REAL'], true) => 'REAL',
            $type === 'DECIMAL' => 'NUMERIC',
            $type === 'BLOB' => 'BLOB',
            default => 'TEXT',
        };
        $sql = "`{$def->name}` {$sqlType}";
        if ($def->autoIncrement) $sql .= ' PRIMARY KEY AUTOINCREMENT';
        elseif ($def->index === 'PRIMARY') $sql .= ' PRIMARY KEY';
        elseif ($def->index === 'UNIQUE') $sql .= ' UNIQUE';
        $sql .= $def->nullable ? ' NULL' : ' NOT NULL';
        if ($def->default !== '') {
            $default = strtoupper($def->default) === 'NOW()' ? 'CURRENT_TIMESTAMP' : $def->default;
            $sql .= $this->buildDefaultClause($default, $def->defaultAsDefined);
        }
        if ($def->unsigned && ($integer || $sqlType === 'REAL' || $sqlType === 'NUMERIC')) {
            $sql .= " CHECK (`{$def->name}` >= 0)";
        }
        if ($type === 'ENUM' && $def->enumValues !== []) {
            $values = array_map(fn(string $value) => "'" . str_replace("'", "''", $value) . "'", $def->enumValues);
            $sql .= " CHECK (`{$def->name}` IN (" . implode(', ', $values) . '))';
        }
        if ($type === 'JSON') $sql .= " CHECK (`{$def->name}` IS NULL OR json_valid(`{$def->name}`))";
        // SQLite has no column comments, length enforcement, or AFTER positioning.
        return $sql;
    }

    private function buildPostgreSQLColumnSQL(ColumnDefinition $def): string
    {
        $type = strtoupper($def->type);
        $sqlType = match ($type) {
            'INT', 'INTEGER', 'MEDIUMINT' => 'INTEGER',
            'TINYINT', 'SMALLINT' => 'SMALLINT',
            'DATETIME' => 'TIMESTAMP', 'DOUBLE' => 'DOUBLE PRECISION',
            'BLOB', 'LONGBLOB', 'MEDIUMBLOB', 'TINYBLOB' => 'BYTEA',
            'LONGTEXT', 'MEDIUMTEXT', 'TINYTEXT', 'ENUM', 'SET' => 'TEXT',
            default => $type,
        };
        if ($def->length !== '' && in_array($sqlType, ['VARCHAR', 'CHAR', 'DECIMAL', 'NUMERIC'], true)) $sqlType .= '(' . $def->length . ')';
        $name = '"' . str_replace('"', '""', $def->name) . '"';
        $sql = $name . ' ' . $sqlType;
        if ($def->autoIncrement) $sql .= ' GENERATED BY DEFAULT AS IDENTITY';
        $sql .= $def->nullable ? ' NULL' : ' NOT NULL';
        if (!$def->autoIncrement && $def->default !== '') $sql .= $this->buildDefaultClause($def->default, $def->defaultAsDefined);
        if ($def->index === 'PRIMARY') $sql .= ' PRIMARY KEY';
        elseif ($def->index === 'UNIQUE') $sql .= ' UNIQUE';
        if ($def->unsigned) $sql .= ' CHECK (' . $name . ' >= 0)';
        if ($type === 'ENUM' && $def->enumValues !== []) {
            $values = array_map(static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'", $def->enumValues);
            $sql .= ' CHECK (' . $name . ' IN (' . implode(', ', $values) . '))';
        }
        if ($type === 'SET') throw new \LogicException('PostgreSQL does not support SET columns. Use TEXT instead.');
        return $sql;
    }

    /**
     * Generate an ADD FOREIGN KEY constraint clause.
     *
     * In a CREATE TABLE this is appended as a trailing element of the column
     * list. In an ALTER TABLE it becomes a separate ADD CONSTRAINT statement.
     *
     * Returns null when the definition carries no foreign key configuration.
     *
     * @param ColumnDefinition $def The column whose foreign key to generate.
     *
     * @return string|null The constraint clause, or null if none is defined.
     */
    public function buildForeignKeySQL(ColumnDefinition $def): ?string
    {
        if ($def->foreignKeyTable === null || $def->foreignKeyColumn === null) {
            return null;
        }

        $sql = "FOREIGN KEY (`{$def->name}`) REFERENCES `{$def->foreignKeyTable}`(`{$def->foreignKeyColumn}`)";
        if ($this->dialect === 'pgsql') $sql = str_replace('`', '"', $sql);

        if ($def->onDelete !== null) {
            $sql .= " ON DELETE {$def->onDelete}";
        }

        if ($def->onUpdate !== null) {
            $sql .= " ON UPDATE {$def->onUpdate}";
        }

        return $sql;
    }

    /**
     * Generate a standalone CREATE INDEX statement for non-inline indexes.
     *
     * Only emits SQL when the definition's index type is INDEX; primary and
     * unique indexes are already inlined by buildColumnSQL() and do not need
     * a separate statement.
     *
     * @param string           $table The table the index belongs to.
     * @param ColumnDefinition $def   The column to index.
     *
     * @return string|null A CREATE INDEX statement, or null when not applicable.
     */
    public function buildIndexSQL(string $table, ColumnDefinition $def): ?string
    {
        if ($def->index !== 'INDEX') {
            return null;
        }

        $indexName = "idx_{$table}_{$def->name}";
        $sql = "CREATE INDEX `{$indexName}` ON `{$table}` (`{$def->name}`)";
        return $this->dialect === 'pgsql' ? str_replace('`', '"', $sql) : $sql;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the DEFAULT … clause, choosing whether to quote the value based
     * on whether it is a known SQL keyword expression.
     */
    private function buildDefaultClause(string $value, bool $asDefined): string
    {
        if ($asDefined || in_array(strtoupper($value), self::UNQUOTED_DEFAULTS, true)) {
            return " DEFAULT {$value}";
        }

        $escaped = str_replace("'", "''", $value);
        return " DEFAULT '{$escaped}'";
    }

    /**
     * Return true when the SQL type keyword accepts the UNSIGNED modifier.
     */
    private function isNumericType(string $type): bool
    {
        return in_array(strtoupper($type), [
            'TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT',
            'FLOAT', 'DOUBLE', 'DECIMAL',
        ], true);
    }
}
