<?php

namespace vfs\storage\providers\default;

use object\ObjectCollection;
use vfs\storage\entry\TransientStorageEntry;
use vfs\storage\entry\enums\EStorageEntryType;
use vfs\storage\providers\interface\ITransientStorageProvider;

class TransientStorage implements ITransientStorageProvider
{
    private string $currentPool;
    private string $currentPoolRoot;

    public function __construct(
        private readonly string $root  = DATA . 'storage' . DIRECTORY_SEPARATOR . 'framework',
        private readonly string $cache = DATA . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'cache',
        private readonly string $temp  = DATA . 'storage' . DIRECTORY_SEPARATOR . 'framework'. DIRECTORY_SEPARATOR . 'temp'
    ) {
        if (!is_dir($this->root))  mkdir($this->root,  0755, true);
        if (!is_dir($this->cache)) mkdir($this->cache, 0755, true);
        if (!is_dir($this->temp))  mkdir($this->temp,  0755, true);
        $this->currentPool     = $this->root;
        $this->currentPoolRoot = $this->root;
    }

    // -------------------------------------------------------------------------
    // Pool
    // -------------------------------------------------------------------------

    /**
     * Selects a top-level pool to work with (root, cache, temp).
     * Resets any previously selected subpool.
     */
    public function selectPool(string $poolName): self
    {
        $this->currentPool     = $this->{$poolName};
        $this->currentPoolRoot = $this->{$poolName};
        return $this;
    }

    /**
     * Selects a subpool within the current pool.
     * Supports nested paths: selectSubPool('uploads/avatars')
     * Creates the directory if it does not exist.
     */
    public function selectSubPool(string $subPoolPath): self
    {
        $path = $this->subPoolPath($subPoolPath);

        if (!is_dir($path))
            mkdir($path, 0755, true);

        $this->currentPool = $path;
        return $this;
    }

    /**
     * Explicitly creates a subpool directory without switching to it.
     */
    public function createSubPool(string $subPoolPath): self
    {
        $path = $this->subPoolPath($subPoolPath);

        if (!is_dir($path))
            mkdir($path, 0755, true);

        return $this;
    }

    /**
     * Deletes a subpool and all its contents.
     * Cannot delete a top-level pool root.
     */
    public function deleteSubPool(string $subPoolPath): bool
    {
        $path = $this->subPoolPath($subPoolPath);

        if (!is_dir($path))
            return false;

        // Reset to pool root if we're currently inside the subpool being deleted
        if (str_starts_with($this->currentPool, $path))
            $this->currentPool = $this->currentPoolRoot;

        return $this->removeDirectory($path);
    }

    /**
     * Returns all immediate subpool directory names in the current pool.
     */
    public function getSubPools(): array
    {
        return array_values(array_filter(
            $this->scanDirectory($this->currentPool),
            fn($file) => is_dir($this->fullPath($file))
        ));
    }

    // -------------------------------------------------------------------------
    // Collection reads
    // -------------------------------------------------------------------------

    public function getTransientStorage(): ObjectCollection
    {
        $oc = new ObjectCollection();
        $oc->setStrictType(TransientStorageEntry::class);

        foreach ($this->scanDirectory($this->currentPool) as $file) {
            $fullPath = $this->fullPath($file);
            if (is_dir($fullPath)) continue;
            $oc->add($this->createTransientStorageEntry($file, $fullPath, $this->extractExtension($file)));
        }

        return $oc;
    }

    public function getTransientStorageEntryNames(): array
    {
        return array_values(array_filter(
            $this->scanDirectory($this->currentPool),
            fn($file) => !is_dir($this->fullPath($file))
        ));
    }

    public function getTransientStorageSize(): float
    {
        $size = 0;

        foreach ($this->scanDirectory($this->currentPool) as $file) {
            $fullPath = $this->fullPath($file);
            if (is_dir($fullPath)) continue;
            $size += filesize($fullPath);
        }

        return $size / (1024 * 1024);
    }

    // -------------------------------------------------------------------------
    // Single entry CRUD
    // -------------------------------------------------------------------------

    public function getTransientStorageEntry(string $name): ?TransientStorageEntry
    {
        foreach ($this->scanDirectory($this->currentPool) as $file) {
            if ($file === $name) {
                $fullPath = $this->fullPath($file);
                return $this->createTransientStorageEntry($file, $fullPath, $this->extractExtension($file));
            }
        }
        return null;
    }

    public function saveTransientStorageEntry(TransientStorageEntry $entry): ?TransientStorageEntry
    {
        $fullPath = $this->fullPath($entry->getFullName());

        if (file_put_contents($fullPath, $entry->getContent() ?? '') === false)
            return null;

        $entry->setPath($fullPath);
        return $entry;
    }

    public function deleteTransientStorageEntry(string $name): bool
    {
        $fullPath = $this->fullPath($name);

        if (!file_exists($fullPath))
            return false;

        return unlink($fullPath);
    }

    public function clearTransientStorage(): bool
    {
        foreach ($this->scanDirectory($this->currentPool) as $file) {
            $fullPath = $this->fullPath($file);
            if (is_dir($fullPath))
                if(!rmdir($fullPath)) return false;
            if (!unlink($fullPath)) return false;
        }
        return true;
    }

    // -------------------------------------------------------------------------
    // Entry mutations
    // -------------------------------------------------------------------------

    public function copyTransientStorageEntry(string $name, string $newName): bool
    {
        $src  = $this->fullPath($name);
        $dest = $this->fullPath($newName);

        if (!file_exists($src))
            return false;

        return copy($src, $dest);
    }

    public function moveTransientStorageEntry(string $name, string $newName): bool
    {
        $src  = $this->fullPath($name);
        $dest = $this->fullPath($newName);

        if (!file_exists($src))
            return false;

        return rename($src, $dest);
    }

    public function renameTransientStorageEntry(string $name, string $newName): bool
    {
        return $this->moveTransientStorageEntry($name, $newName);
    }

    // -------------------------------------------------------------------------
    // Create / make / upload / update
    // -------------------------------------------------------------------------

    public function createTransientStorageEntry(string $name, string $path, string $extension = ''): TransientStorageEntry
    {
        $entry = new TransientStorageEntry();
        $entry->setName($name)
            ->setPath($path)
            ->setExtension($extension)
            ->setType(EStorageEntryType::fromExtension($extension));
        return $entry;
    }

    public function makeTransientStorageEntry(string $name, string $extension, string $content): ?TransientStorageEntry
    {
        $entry = $this->createTransientStorageEntry($name, '', $extension);
        $entry->setContent($content);
        return $this->saveTransientStorageEntry($entry);
    }

    public function uploadTransientStorageEntry(string $name, string $uploadPath, string $extension): ?TransientStorageEntry
    {
        if (!file_exists($uploadPath))
            return null;

        $fullPath = $this->fullPath($name . ($extension ? '.' . $extension : ''));

        if (!copy($uploadPath, $fullPath))
            return null;

        $entry = $this->createTransientStorageEntry($name, $fullPath, $extension);
        $entry->setUploadPath($uploadPath);
        return $entry;
    }

    public function updateTransientStorageEntryContent(string $name, string $content): ?TransientStorageEntry
    {
        $fullPath = $this->fullPath($name);

        if (!file_exists($fullPath))
            return null;

        if (file_put_contents($fullPath, $content) === false)
            return null;

        $entry = $this->createTransientStorageEntry($name, $fullPath, $this->extractExtension($name));
        $entry->setContent($content);
        return $entry;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function subPoolPath(string $subPoolPath): string
    {
        return $this->currentPoolRoot . DIRECTORY_SEPARATOR . ltrim($subPoolPath, DIRECTORY_SEPARATOR);
    }

    private function fullPath(string $file): string
    {
        return $this->currentPool . DIRECTORY_SEPARATOR . $file;
    }

    private function extractExtension(string $filename): string
    {
        return pathinfo($filename, PATHINFO_EXTENSION) ?? '';
    }

    private function scanDirectory(string $path): array
    {
        $scan = scandir($path);
        unset($scan[0], $scan[1]);
        return array_values($scan);
    }

    private function removeDirectory(string $path): bool
    {
        foreach ($this->scanDirectory($path) as $file) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $file;
            is_dir($fullPath) ? $this->removeDirectory($fullPath) : unlink($fullPath);
        }
        return rmdir($path);
    }
}