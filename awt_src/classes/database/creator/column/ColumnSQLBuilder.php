<?php

namespace database\creator\column;

/**
 * ColumnSQLBuilder
 *
 * Sole responsibility: generate SQL fragments from a ColumnDefinition.
 * It holds no state of its own; every method is a pure transformation of
 * the definition it receives.
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
        return "CREATE INDEX `{$indexName}` ON `{$table}` (`{$def->name}`)";
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
