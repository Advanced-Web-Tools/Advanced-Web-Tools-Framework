<?php

namespace vfs\storage\services;


use vfs\storage\interfaces\IFileSystemService;

/**
 * Class LocalFileSystemService
 *
 * Concrete implementation of IFileSystemService for the local filesystem (SRP).
 * All raw PHP file I/O is isolated here — no other class calls file_exists(),
 * rename(), unlink(), etc. directly.
 */
class LocalFileSystemService implements IFileSystemService
{
    public function move(string $sourcePath, string $destinationPath): bool
    {
        return rename($sourcePath, $destinationPath);
    }

    public function copy(string $sourcePath, string $destinationPath): bool
    {
        return copy($sourcePath, $destinationPath);
    }

    public function delete(string $path): bool
    {
        return unlink($path);
    }

    public function rename(string $sourcePath, string $destinationPath): bool
    {
        return rename($sourcePath, $destinationPath);
    }

    public function fileSize(string $path): int
    {
        $size = filesize($path);
        if ($size === false) {
            throw new \RuntimeException("Unable to determine file size for: {$path}");
        }
        return $size;
    }

    public function lastModified(string $path): int
    {
        $mtime = filemtime($path);
        if ($mtime === false) {
            throw new \RuntimeException("Unable to determine last modified time for: {$path}");
        }
        return $mtime;
    }

    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function makeDirectory(string $path, int $permissions = 0755, bool $recursive = true): bool
    {
        if ($this->exists($path)) {
            return true;
        }
        return mkdir($path, $permissions, $recursive);
    }
}
