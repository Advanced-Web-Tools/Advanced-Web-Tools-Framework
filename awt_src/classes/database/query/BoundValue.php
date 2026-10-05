<?php
namespace database\query;

/** Force a literal value, including the legacy reserved string DEFAULT. */
final readonly class BoundValue
{
    public function __construct(public mixed $value) {}
}
