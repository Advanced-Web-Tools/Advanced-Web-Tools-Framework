<?php

namespace vfs\storage\entry\middleware;

use vfs\storage\entry\StorageEntry;

interface IStorageEntryMiddleware
{
    /**
     * If true, the handle method will not be called.
     * @return bool
     */
    public function doNotHandle(): bool;

    /**
     * If true, the validate method will not be called.
     * @return bool
     */
    public function doNotValidate(): bool;

    /**
     * Handles file operations on successful CRUD operations.
     * @return StorageEntry|null
     */
    public function handle(StorageEntry $entry): ?StorageEntry;

    /**
     * Validates file operations before CRUD operations.
     *
     * Can be used to check if the file is writable, permission, etc.
     * @return bool
     */
    public function validate(): bool;
}