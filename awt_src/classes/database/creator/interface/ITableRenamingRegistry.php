<?php

namespace database\creator\interface;

/** Optional capability to preserve registry IDs and column entries on rename. */
interface ITableRenamingRegistry extends ITableRegistry
{
    public function renameTable(string $from, string $to): void;
}
