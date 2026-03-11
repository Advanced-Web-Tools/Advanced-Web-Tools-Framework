<?php

namespace vfs\storage\interfaces;

use vfs\storage\StorageEntry;

/**
 * Interface IStorageManager
 *
 * Defines the public API for storage orchestration (ISP).
 * Clients depend only on the methods they actually use.
 */
interface IStorageManager
{
    public function get(int $id): StorageEntry;

    public function move(int $id, string $location): bool;

    public function delete(int $id): bool;

    public function rename(int $id, string $name): bool;

    public function copy(int $id): StorageEntry;

    public function registerLocal(StorageEntry $entry, string $ownerName): bool;
}
