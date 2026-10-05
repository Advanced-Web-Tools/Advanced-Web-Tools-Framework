<?php

namespace database\creator\column;

/**
 * ColumnDefinition
 *
 * Immutable value object that describes a single database column and its
 * constraints. Constructed exclusively by ColumnCreator and consumed by
 * ColumnSQLBuilder. No class other than those two should create instances
 * directly.
 *
 * Every property is read-only after construction, making it safe to pass
 * between collaborators without defensive copying.
 */
final class ColumnDefinition
{
    /**
     * @param string      $name               Column name.
     * @param string      $type               SQL type keyword: INT, VARCHAR, TEXT, etc.
     * @param string      $length             Length/precision qualifier, e.g. "11" or "10,2".
     *                                        Empty when the type does not accept a length.
     * @param bool        $nullable           When true, the column accepts NULL values.
     * @param string      $default            Default value expression. Empty means no DEFAULT clause.
     * @param bool        $defaultAsDefined   When true, $default is emitted verbatim without quoting
     *                                        (e.g. for expressions like CURRENT_TIMESTAMP or NOW()).
     * @param bool        $autoIncrement      Adds AUTO_INCREMENT to the column definition.
     * @param bool        $unsigned           Adds UNSIGNED to numeric column definitions.
     * @param string      $index              Index type to inline: PRIMARY, UNIQUE, INDEX, or empty.
     * @param string      $comment            Optional column comment stored in the schema.
     * @param string      $after              When non-empty, appends AFTER `column` to ALTER TABLE
     *                                        ADD COLUMN statements for explicit column positioning.
     * @param array       $enumValues         Ordered list of allowed values for ENUM and SET columns.
     * @param string|null $foreignKeyTable    Target table for a FOREIGN KEY constraint, or null.
     * @param string|null $foreignKeyColumn   Target column for a FOREIGN KEY constraint, or null.
     * @param string|null $onDelete           ON DELETE action: CASCADE, SET NULL, RESTRICT, etc.
     * @param string|null $onUpdate           ON UPDATE action: CASCADE, SET NULL, RESTRICT, etc.
     */
    public function __construct(
        public readonly string  $name,
        public readonly string  $type,
        public readonly string  $length             = '',
        public readonly bool    $nullable            = false,
        public readonly string  $default             = '',
        public readonly bool    $defaultAsDefined    = false,
        public readonly bool    $autoIncrement       = false,
        public readonly bool    $unsigned            = false,
        public readonly string  $index               = '',
        public readonly string  $comment             = '',
        public readonly string  $after               = '',
        public readonly array   $enumValues          = [],
        public readonly ?string $foreignKeyTable     = null,
        public readonly ?string $foreignKeyColumn    = null,
        public readonly ?string $onDelete            = null,
        public readonly ?string $onUpdate            = null,
    ) {}
}
