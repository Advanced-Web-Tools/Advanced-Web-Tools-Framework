<?php

namespace vfs\storage\interfaces;

/**
 * Interface IFileSystemService
 *
 * Abstracts all file system I/O operations (SRP + DIP).
 * StorageManager depends on this contract, not a concrete implementation.
 */
interface IFileSystemService
{
    public function move(string $sourcePath, string $destinationPath): bool;

    public function copy(string $sourcePath, string $destinationPath): bool;

    public function delete(string $path): bool;

    public function rename(string $sourcePath, string $destinationPath): bool;

    public function fileSize(string $path): int;

    public function lastModified(string $path): int;

    public function exists(string $path): bool;

    public function makeDirectory(string $path, int $permissions = 0755, bool $recursive = true): bool;
}
