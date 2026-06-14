<?php

namespace vfs\transient\interfaces;

use vfs\transient\enums\ETransientType;

interface ITransientStorage
{
    public function setPool(string $pool): self;

    public function setSubPool(string $subPool): self;

    public function getFile(string $name): ?ITransientStorageEntry;

    public function createFile(
        string $name,
        ETransientType $type,
        string|array $content
    ): ITransientStorageEntry;

    public function deleteFile(ITransientStorageEntry $file): bool;

    public function renameFile(
        ITransientStorageEntry $file,
        string $newName
    ): ITransientStorageEntry;

    public function moveFile(
        ITransientStorageEntry $file,
        string $newPool
    ): ITransientStorageEntry;

    public function copyFile(
        ITransientStorageEntry $file,
        string $newName
    ): ITransientStorageEntry;

    /**
     * @return ITransientStorageEntry[]
     */
    public function getFiles(): array;
}
