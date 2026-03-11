<?php

namespace vfs\storage\interfaces;

use vfs\storage\StorageEntry;
use vfs\storage\enums\EOwnerType;

/**
 * Interface IStorageRepository
 *
 * Defines the persistence contract for StorageEntry objects (DIP).
 * High-level modules (StorageManager) depend on this abstraction,
 * not the concrete StorageRepository class.
 */
interface IStorageRepository
{
    public function fetchAll(): array;

    public function fetchById(int $id): StorageEntry;

    public function fetchByOwner(int $ownerId): array;

    public function fetchByName(string $name): array;

    public function fetchByOwnerType(EOwnerType $ownerType): array;

    public function fetchByOwnerTypeAndOwner(EOwnerType $ownerType, int $ownerId): array;

    public function fetchByOwnerAndName(int $ownerId, string $name): ?StorageEntry;

    public function create(StorageEntry $entry): StorageEntry;

    public function update(StorageEntry $entry): bool;

    public function delete(StorageEntry $entry): bool;
}
