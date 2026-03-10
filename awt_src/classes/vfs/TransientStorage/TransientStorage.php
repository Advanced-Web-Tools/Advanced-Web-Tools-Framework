<?php

namespace vfs\TransientStorage;

use vfs\TransientStorage\enums\ETransientType;

class TransientStorage
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
            if (!is_dir($pool)) {
                mkdir($pool, 0755, true);
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

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $this->currentPool = $path . DIRECTORY_SEPARATOR;

        return $this;
    }

    public function getFile(string $name): ?TransientStorageEntry
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
    ): TransientStorageEntry {

        $path = $this->currentPool . $name . "." . $type->value;

        $entry = new TransientStorageEntry($name, $path);
        $entry->write($content);

        return $entry;
    }

    public function deleteFile(TransientStorageEntry $file): bool
    {
        return $file->delete();
    }

    public function renameFile(
        TransientStorageEntry $file,
        string $newName
    ): TransientStorageEntry {

        $newPath = dirname($file->path) . DIRECTORY_SEPARATOR . $newName;

        rename($file->path, $newPath);

        return new TransientStorageEntry($newName, $newPath);
    }

    public function moveFile(
        TransientStorageEntry $file,
        string $newPool
    ): TransientStorageEntry {

        $target = $this->pools[$newPool] . DIRECTORY_SEPARATOR . basename($file->path);

        rename($file->path, $target);

        return new TransientStorageEntry(basename($target), $target);
    }

    public function copyFile(
        TransientStorageEntry $file,
        string $newName
    ): TransientStorageEntry {

        $target = $this->currentPool . $newName;

        copy($file->path, $target);

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