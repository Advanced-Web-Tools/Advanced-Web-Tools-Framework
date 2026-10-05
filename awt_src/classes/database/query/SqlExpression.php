<?php
namespace database\query;

/** Explicit supported SQL defaults; ordinary values remain bound parameters. */
final readonly class SqlExpression
{
    public string $sql;
    public function __construct(string $sql)
    {
        $sql = strtoupper($sql);
        if (!in_array($sql, ['DEFAULT', 'CURRENT_TIMESTAMP'], true)) {
            throw new \InvalidArgumentException('Unsupported SQL expression.');
        }
        $this->sql = $sql;
    }
}
