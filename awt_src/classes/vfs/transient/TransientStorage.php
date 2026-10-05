<?php

namespace vfs\transient;

use vfs\transient\enums\ETransientType;
use vfs\transient\interfaces\ITransientStorage;
use vfs\transient\interfaces\ITransientStorageEntry;

class TransientStorage implements ITransientStorage
{
    private string $currentPool;
    private string $subPool;
    private string $basePool;
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
        $this->basePool = $this->pools["cache"];
        $this->currentPool = $this->basePool . DIRECTORY_SEPARATOR;
    }

    public function setPool(string $pool): self
    {
        if (!isset($this->pools[$pool])) throw new \InvalidArgumentException("Unknown transient pool: {$pool}");
        $this->basePool = $this->pools[$pool];
        $this->currentPool = $this->basePool . DIRECTORY_SEPARATOR;
        $this->subPool = '';
        return $this;
    }

    public function setSubPool(string $subPool): self
    {
        $this->assertRelativePath($subPool, true);
        $this->subPool = DIRECTORY_SEPARATOR . trim($subPool, DIRECTORY_SEPARATOR);

        $path = $this->basePool . $this->subPool;

        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
        }

        $this->currentPool = $path . DIRECTORY_SEPARATOR;

        return $this;
    }

    public function getFile(string $name): ?ITransientStorageEntry
    {
        $this->assertRelativePath($name);
        $path = $this->currentPool . $name;

        if (!is_file($path)) {
            return null;
        }

        return new TransientStorageEntry($name, $path);
    }

    public function createFile(
        string $name,
        ETransientType $type,
        string|array $content
    ): ITransientStorageEntry {

        $this->assertRelativePath($name);
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

        $this->assertRelativePath($newName);
        $newPath = dirname($file->getPath()) . DIRECTORY_SEPARATOR . $newName;

        if (!(new \vfs\storage\services\LocalFileSystemService())->move($file->getPath(), $newPath)) throw new \RuntimeException('Could not rename transient file.');

        return new TransientStorageEntry($newName, $newPath);
    }

    public function moveFile(
        ITransientStorageEntry $file,
        string $newPool
    ): ITransientStorageEntry {

        if (!isset($this->pools[$newPool])) throw new \InvalidArgumentException("Unknown transient pool: {$newPool}");
        $target = $this->pools[$newPool] . DIRECTORY_SEPARATOR . basename($file->getPath());

        if (!(new \vfs\storage\services\LocalFileSystemService())->move($file->getPath(), $target)) throw new \RuntimeException('Could not move transient file.');

        return new TransientStorageEntry(basename($target), $target);
    }

    public function copyFile(
        ITransientStorageEntry $file,
        string $newName
    ): ITransientStorageEntry {

        $this->assertRelativePath($newName);
        $target = $this->currentPool . $newName;

        if (!(new \vfs\storage\services\LocalFileSystemService())->copy($file->getPath(), $target)) throw new \RuntimeException('Could not copy transient file.');

        return new TransientStorageEntry($newName, $target);
    }

    public function getFiles(): array
    {
        return $this->scan($this->currentPool);
    }

    private function scan(string $path): array
    {
        $scannedFiles = [];

        $names = scandir($path);
        if ($names === false) throw new \RuntimeException("Cannot scan transient directory: {$path}");
        $files = array_diff($names, ['.', '..']);

        foreach ($files as $file) {

            $full = $path . DIRECTORY_SEPARATOR . $file;

            if (is_dir($full) && !is_link($full)) {
                $scannedFiles = array_merge($scannedFiles, $this->scan($full));
            } elseif (is_file($full)) {
                $scannedFiles[] =
                    new TransientStorageEntry($file, $full);
            }
        }

        return $scannedFiles;
    }

    private function assertRelativePath(string $name, bool $allowEmpty = false): void
    {
        if (($name === '' && !$allowEmpty) || str_contains($name, "\0") || str_contains($name, '\\')
            || str_starts_with($name, '/') || preg_match('/^[a-z]:/i', $name)) {
            throw new \InvalidArgumentException('Transient paths must be relative.');
        }
        foreach (explode('/', $name) as $part) {
            if ($part === '..' || $part === '.') throw new \InvalidArgumentException('Transient paths cannot contain traversal segments.');
        }
    }
}
