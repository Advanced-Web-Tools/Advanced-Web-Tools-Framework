<?php

namespace vfs\storage\providers\interface;

use vfs\storage\entry\StorageEntry;

interface IStorageProvider
{
    public function save(StorageEntry $entry): ?StorageEntry;
    public function delete(StorageEntry $entry): bool;
    public function move(StorageEntry $entry, string $location): ?StorageEntry;
    public function copy(StorageEntry $entry, string $location): ?StorageEntry;
    public function rename(StorageEntry $entry, string $newName): ?StorageEntry;
    public function get(StorageEntry $entry): ?StorageEntry;
    public function buildLocation(StorageEntry $entry, bool $local = false): ?string;
    public function getSize(StorageEntry $entry): int;
}