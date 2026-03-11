<?php

namespace vfs\storage\interfaces;

use vfs\storage\StorageEntry;
use vfs\storage\enums\EOwnerType;

/**
 * Interface IOwnerStorageStrategy
 *
 * Defines the contract for owner-specific storage registration logic (OCP).
 *
 * Adding support for a new EOwnerType no longer requires modifying
 * StorageManager. Instead, a new strategy class implementing this
 * interface is created and registered — open for extension, closed
 * for modification.
 */
interface IOwnerStorageStrategy
{
    /**
     * Returns the EOwnerType this strategy handles.
     */
    public function supports(): EOwnerType;

    /**
     * Executes the storage registration for this owner type.
     */
    public function register(StorageEntry $entry, string $ownerName): bool;
}
