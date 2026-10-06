<?php
namespace database\interface;

/** Optional capability; existing IProvider implementations remain compatible. */
interface ITransactionalProvider extends IProvider
{
    public function transaction(callable $callback): mixed;
    public function inTransaction(): bool;
    public function afterCommit(callable $callback): void;
}
