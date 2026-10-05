<?php

namespace vfs\transient;

use vfs\transient\enums\ETransientType;
use vfs\transient\interfaces\ITransientStorage;
use vfs\transient\interfaces\ITransientStorageEntry;

class TransientStorage implements ITransientStorage
{
    private string $currentPool;
    private string $subPool;
    private array $scannedFiles = [];
    private readonly array $pools;

    public function __construct()
    {
        $this->pools = [
            "cache" => DATA . "storage/framework/cache",
            "temp" => DATA . "storage/framework/temp",
        ];

        foreach ($this->pools as $pool) {
            if (!is_dir($pool) && !mkdir($pool, 0755, true) && !is_dir($pool)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $pool));
            }
        }

        $this->subPool = DIRECTORY_SEPARATOR;
        $this->currentPool = $this->pools["cache"] . DIRECTORY_SEPARATOR;
    }

    public function setPool(string $pool): self
    {
        $this->currentPool = $this->pools[$pool] . DIRECTORY_SEPARATOR;
        return $this;
    }

    public function setSubPool(string $subPool): self
    {
        $this->subPool = DIRECTORY_SEPARATOR . trim($subPool, DIRECTORY_SEPARATOR);

        $path = $this->currentPool . $this->subPool;

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        $this->currentPool = $path . DIRECTORY_SEPARATOR;

        return $this;
    }

    public function getFile(string $name): ?ITransientStorageEntry
    {
        $path = $this->currentPool . $name;

        if (!file_exists($path)) {
            return null;
        }

        return new TransientStorageEntry($name, $path);
    }

    public function createFile(
        string $name,
        ETransientType $type,
        string|array $content
    ): ITransientStorageEntry {

        $path = $this->currentPool . $name . "." . $type->value;

        $entry = new TransientStorageEntry($name, $path);
        $entry->write($content);

        return $entry;
    }

    public function deleteFile(ITransientStorageEntry $file): bool
    {
        return $file->delete();
    }

    public function renameFile(
        ITransientStorageEntry $file,
        string $newName
    ): ITransientStorageEntry {

        $newPath = dirname($file->getPath()) . DIRECTORY_SEPARATOR . $newName;

        rename($file->getPath(), $newPath);

        return new TransientStorageEntry($newName, $newPath);
    }

    public function moveFile(
        ITransientStorageEntry $file,
        string $newPool
    ): ITransientStorageEntry {

        $target = $this->pools[$newPool] . DIRECTORY_SEPARATOR . basename($file->getPath());

        rename($file->getPath(), $target);

        return new TransientStorageEntry(basename($target), $target);
    }

    public function copyFile(
        ITransientStorageEntry $file,
        string $newName
    ): ITransientStorageEntry {

        $target = $this->currentPool . $newName;

        copy($file->getPath(), $target);

        return new TransientStorageEntry($newName, $target);
    }

    public function getFiles(): array
    {
        return $this->scan($this->currentPool);
    }

    private function scan(string $path): array
    {
        $this->scannedFiles = [];

        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {

            $full = $path . DIRECTORY_SEPARATOR . $file;

            if (is_dir($full)) {
                $this->scan($full);
            } else {
                $this->scannedFiles[] =
                    new TransientStorageEntry($file, $full);
            }
        }

        return $this->scannedFiles;
    }
}