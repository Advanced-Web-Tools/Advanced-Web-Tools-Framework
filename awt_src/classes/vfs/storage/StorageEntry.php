<?php

namespace vfs\storage;

use database\DatabaseManager;
use vfs\storage\enums\EStorageFileType;
use vfs\storage\enums\EStorageOwnerType;
use vfs\storage\interface\IStorageEntryMiddleware;
use vfs\storage\interface\IStorageProvider;

class StorageEntry
{
    public string $name;
    public EStorageFileType $type;
    public IStorageProvider $provider;
    public ?EStorageOwnerType $owner;
    public ?string $ownerName;
    public ?IStorageEntryMiddleware $middleware;

    public string $path;
    public string $systemPath;

    public bool $ignoreDB = false;
}