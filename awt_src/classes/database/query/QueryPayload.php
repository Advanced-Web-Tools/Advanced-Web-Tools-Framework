<?php

namespace database\query;

/**
 * Immutable value object that carries everything needed to execute a query.
 *
 * @property-read string $sql         Prepared-statement SQL string.
 * @property-read array  $bindings    Placeholder → value map for PDO binding.
 * @property-read string $table       The primary table this query targets.
 * @property-read array  $conditions  Raw column → value pairs from the WHERE clause,
 *                                    used by the cache layer for fine-grained invalidation.
 */
final class QueryPayload
{
    public function __construct(
        public readonly string $sql,
        public readonly array  $bindings,
        public readonly string $table,
        public readonly array  $conditions = [],
    ) {}
}
