<?php

namespace vfs\storage;

use database\DatabaseManager;
use vfs\storage\enums\EStorageFileType;
use vfs\storage\enums\EStorageOwnerType;
use vfs\storage\interface\IStorageEntryMiddleware;
use vfs\storage\interface\IStorageProvider;

/**
 * Class Storage
 * Responsible for managing storage operations and providing access to storage resources via providers.
 * It fetches storage entries from database.
 */
class Storage
{
    /**
     * Context of the storage passed to middleware for better identification.
     * @var ?string $context
     */
    public ?string $context;

    public StorageEntry $entry;

    private DatabaseManager $database;

    public function __construct()
    {
        $this->database = new DatabaseManager();
    }

    static public function upload(string $ownerName, EStorageFileType $type, IStorageProvider $provider, EStorageOwnerType $ownerType = EStorageOwnerType::System, ?IStorageEntryMiddleware $entryMiddleware = null): ?StorageEntry
    {

        return null;
    }

    static public function findInFileSystem(): ?StorageEntry
    {

        return null;
    }

    public function save(): string|null
    {

        return null;
    }

    public function delete(): bool
    {

        return false;
    }

    public function exists(StorageEntry $entry): bool
    {
        return false;
    }
}