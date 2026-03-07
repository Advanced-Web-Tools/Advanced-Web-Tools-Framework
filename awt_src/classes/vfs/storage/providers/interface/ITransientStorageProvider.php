<?php

namespace vfs\storage\providers\interface;

use object\ObjectCollection;
use vfs\storage\entry\TransientStorageEntry;

interface ITransientStorageProvider
{
    public function selectPool(string $poolName): self;
    public function selectSubPool(string $subPoolPath): self;
    public function createSubPool(string $subPoolPath): self;
    public function deleteSubPool(string $subPoolPath): bool;
    public function getSubPools(): array;

    public function getTransientStorage(): ObjectCollection;
    public function getTransientStorageEntryNames(): array;
    public function getTransientStorageSize(): float;

    public function getTransientStorageEntry(string $name): ?TransientStorageEntry;
    public function saveTransientStorageEntry(TransientStorageEntry $entry): ?TransientStorageEntry;
    public function deleteTransientStorageEntry(string $name): bool;
    public function clearTransientStorage(): bool;

    public function copyTransientStorageEntry(string $name, string $newName): bool;
    public function moveTransientStorageEntry(string $name, string $newName): bool;
    public function renameTransientStorageEntry(string $name, string $newName): bool;

    public function createTransientStorageEntry(string $name, string $path, string $extension): TransientStorageEntry;
    public function makeTransientStorageEntry(string $name, string $extension, string $content): ?TransientStorageEntry;
    public function uploadTransientStorageEntry(string $name, string $uploadPath, string $extension): ?TransientStorageEntry;
    public function updateTransientStorageEntryContent(string $name, string $content): ?TransientStorageEntry;
}