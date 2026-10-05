<?php

namespace database\creator;

use database\creator\column\ColumnDefinition;
use database\creator\column\ColumnSQLBuilder;

/**
 * ColumnCreator
 *
 * Facade that exposes a fluent, readable API for defining database columns.
 * Every static factory method returns a new instance pre-configured with
 * the chosen SQL type. Modifier methods then chain onto that instance to
 * add constraints, defaults, indexes, and foreign keys.
 *
 * Internally, all state is accumulated into a ColumnDefinition value object
 * and all SQL generation is delegated to ColumnSQLBuilder. ColumnCreator
 * itself contains no SQL logic.
 *
 * Usage:
 *
 *   ColumnCreator::INT('user_id', '11')
 *       ->unsigned()
 *       ->foreignKey('users', 'id', onDelete: 'CASCADE')
 *
 *   ColumnCreator::VARCHAR('slug', '255')
 *       ->unique()
 *       ->default('untitled')
 *
 *   ColumnCreator::DECIMAL('price', '10,2')
 *       ->unsigned()
 *       ->default('0.00')
 *
 *   ColumnCreator::ENUM('status', ['draft', 'published', 'archived'])
 *       ->default('draft')
 */
class ColumnCreator
{
    // Mutable properties accumulated during the fluent chain.
    // On build() these are sealed into an immutable ColumnDefinition.

    private string  $name             = '';
    private string  $type             = '';
    private string  $length           = '';
    private bool    $nullable         = false;
    private string  $default          = '';
    private bool    $defaultAsDefined = false;
    private bool    $autoIncrement    = false;
    private bool    $unsigned         = false;
    private string  $index            = '';
    private string  $comment          = '';
    private string  $after            = '';
    private array   $enumValues       = [];
    private ?string $fkTable          = null;
    private ?string $fkColumn         = null;
    private ?string $onDelete         = null;
    private ?string $onUpdate         = null;

    private ColumnSQLBuilder $sqlBuilder;

    private function __construct()
    {
        $this->sqlBuilder = new ColumnSQLBuilder();
    }

    // =========================================================================
    // Static factory methods — one per supported SQL type
    // =========================================================================

    /** Standard signed/unsigned integer. Pass length as a string, e.g. "11". */
    public static function INT(string $name, string $length = '11'): self
    {
        return self::make($name, 'INT', $length);
    }

    /** 1-byte integer. Commonly used for boolean flags as TINYINT(1). */
    public static function TINYINT(string $name, string $length = '1'): self
    {
        return self::make($name, 'TINYINT', $length);
    }

    /** 2-byte integer. */
    public static function SMALLINT(string $name, string $length = '5'): self
    {
        return self::make($name, 'SMALLINT', $length);
    }

    /** 8-byte integer. Use for IDs on large tables. */
    public static function BIGINT(string $name, string $length = '20'): self
    {
        return self::make($name, 'BIGINT', $length);
    }

    /**
     * Boolean column stored as TINYINT(1).
     * Defaults to 0 (false) unless overridden with ->default('1').
     */
    public static function BOOLEAN(string $name): self
    {
        return self::make($name, 'TINYINT', '1')->default('0');
    }

    /** Single-precision floating point. */
    public static function FLOAT(string $name): self
    {
        return self::make($name, 'FLOAT');
    }

    /** Double-precision floating point. */
    public static function DOUBLE(string $name): self
    {
        return self::make($name, 'DOUBLE');
    }

    /**
     * Fixed-precision decimal. Pass precision and scale as "precision,scale",
     * e.g. "10,2" for a number up to 99999999.99.
     */
    public static function DECIMAL(string $name, string $precisionScale = '10,2'): self
    {
        return self::make($name, 'DECIMAL', $precisionScale);
    }

    /** Variable-length string up to 65,535 bytes. Length is required. */
    public static function VARCHAR(string $name, string $length): self
    {
        return self::make($name, 'VARCHAR', $length);
    }

    /** Fixed-length string. Pads shorter values with spaces. */
    public static function CHAR(string $name, string $length): self
    {
        return self::make($name, 'CHAR', $length);
    }

    /** Up to 65,535 characters of text. */
    public static function TEXT(string $name): self
    {
        return self::make($name, 'TEXT');
    }

    /** Up to 16,777,215 characters of text. */
    public static function MEDIUMTEXT(string $name): self
    {
        return self::make($name, 'MEDIUMTEXT');
    }

    /** Up to 4,294,967,295 characters of text. */
    public static function LONGTEXT(string $name): self
    {
        return self::make($name, 'LONGTEXT');
    }

    /**
     * DATE stores year, month, and day (no time component).
     * Use DATETIME() or TIMESTAMP() when time is needed.
     */
    public static function DATE(string $name): self
    {
        return self::make($name, 'DATE');
    }

    /** Date and time stored in the database timezone. Range: 1000-01-01 to 9999-12-31. */
    public static function DATETIME(string $name): self
    {
        return self::make($name, 'DATETIME');
    }

    /**
     * Date and time stored as UTC, displayed in the connection timezone.
     * Range: 1970-01-01 to 2038-01-19.
     */
    public static function TIMESTAMP(string $name): self
    {
        return self::make($name, 'TIMESTAMP');
    }

    /**
     * Enforces that the column value is one of the provided strings.
     *
     * @param string   $name   Column name.
     * @param string[] $values Allowed values.
     */
    public static function ENUM(string $name, array $values): self
    {
        $instance             = self::make($name, 'ENUM');
        $instance->enumValues = $values;
        return $instance;
    }

    /**
     * Allows storing zero or more values from the provided set, as a
     * comma-separated string.
     *
     * @param string   $name   Column name.
     * @param string[] $values Allowed values.
     */
    public static function SET(string $name, array $values): self
    {
        $instance             = self::make($name, 'SET');
        $instance->enumValues = $values;
        return $instance;
    }

    /** Binary large object, up to 65,535 bytes. */
    public static function BLOB(string $name): self
    {
        return self::make($name, 'BLOB');
    }

    /**
     * JSON column with native validation and path-expression support
     * (MySQL 5.7.8+ / MariaDB 10.2+).
     */
    public static function JSON(string $name): self
    {
        return self::make($name, 'JSON');
    }

    // =========================================================================
    // Modifier methods
    // =========================================================================

    /**
     * Set a DEFAULT value for the column.
     *
     * @param string $value      The default value or expression.
     * @param bool   $asDefined  When true, the value is emitted verbatim without
     *                           quoting. Use for SQL expressions such as NOW() or
     *                           custom raw expressions not in the built-in list.
     */
    public function default(string $value, bool $asDefined = false): self
    {
        $this->default          = $value;
        $this->defaultAsDefined = $asDefined;
        return $this;
    }

    /** Mark the column AUTO_INCREMENT. Only valid on integer primary keys. */
    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    /** Allow NULL values. Omitting this makes the column NOT NULL. */
    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    /**
     * Add UNSIGNED to the column definition.
     * Silently ignored for non-numeric types.
     */
    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    /** Mark this column as the PRIMARY KEY. */
    public function primary(): self
    {
        $this->index = 'PRIMARY';
        return $this;
    }

    /** Add a UNIQUE constraint to the column. */
    public function unique(): self
    {
        $this->index = 'UNIQUE';
        return $this;
    }

    /**
     * Create a regular (non-unique) index on this column.
     * The index is emitted as a separate CREATE INDEX statement by TableWizard
     * rather than being inlined into the column definition.
     */
    public function index(): self
    {
        $this->index = 'INDEX';
        return $this;
    }

    /**
     * Add a human-readable COMMENT to the column, stored in information_schema.
     *
     * @param string $text The comment text.
     */
    public function comment(string $text): self
    {
        $this->comment = $text;
        return $this;
    }

    /**
     * Position this column immediately after another in ALTER TABLE ADD COLUMN
     * statements. Has no effect inside CREATE TABLE (column order is determined
     * by the order columns are added to TableWizard).
     *
     * @param string $columnName The column this one should follow.
     */
    public function after(string $columnName): self
    {
        $this->after = $columnName;
        return $this;
    }

    /**
     * Define a FOREIGN KEY constraint on this column.
     *
     * @param string      $referenceTable  The table being referenced.
     * @param string      $referenceColumn The column in the referenced table.
     * @param string|null $onDelete        ON DELETE action: CASCADE, SET NULL, RESTRICT, NO ACTION.
     * @param string|null $onUpdate        ON UPDATE action: CASCADE, SET NULL, RESTRICT, NO ACTION.
     */
    public function foreignKey(
        string  $referenceTable,
        string  $referenceColumn,
        ?string $onDelete = null,
        ?string $onUpdate = null,
    ): self {
        $this->fkTable  = $referenceTable;
        $this->fkColumn = $referenceColumn;
        $this->onDelete = $onDelete;
        $this->onUpdate = $onUpdate;
        return $this;
    }

    // =========================================================================
    // Build and SQL generation
    // =========================================================================

    /**
     * Seal the accumulated state into an immutable ColumnDefinition.
     * Called internally by TableWizard; rarely needed in application code.
     */
    public function build(): ColumnDefinition
    {
        return new ColumnDefinition(
            name:             $this->name,
            type:             $this->type,
            length:           $this->length,
            nullable:         $this->nullable,
            default:          $this->default,
            defaultAsDefined: $this->defaultAsDefined,
            autoIncrement:    $this->autoIncrement,
            unsigned:         $this->unsigned,
            index:            $this->index,
            comment:          $this->comment,
            after:            $this->after,
            enumValues:       $this->enumValues,
            foreignKeyTable:  $this->fkTable,
            foreignKeyColumn: $this->fkColumn,
            onDelete:         $this->onDelete,
            onUpdate:         $this->onUpdate,
        );
    }

    /**
     * Generate the column definition SQL fragment.
     * Delegates to ColumnSQLBuilder; no SQL logic lives here.
     */
    public function generateColumnSQL(): string
    {
        return $this->sqlBuilder->buildColumnSQL($this->build());
    }

    /**
     * Generate the FOREIGN KEY constraint clause, or null when no foreign key
     * is defined. Delegates to ColumnSQLBuilder.
     */
    public function generateForeignKeySQL(): ?string
    {
        return $this->sqlBuilder->buildForeignKeySQL($this->build());
    }

    /**
     * Return the column name. Used by TableWizard for registry lookups and
     * duplicate detection without needing to build the full definition.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Return the SQL type keyword. Used by TableWizard when recording the column
     * in the schema registry.
     */
    public function getType(): string
    {
        return $this->type;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /** Centralised instance factory used by all static type methods. */
    private static function make(string $name, string $type, string $length = ''): self
    {
        $instance         = new self();
        $instance->name   = $name;
        $instance->type   = $type;
        $instance->length = $length;
        return $instance;
    }
}
