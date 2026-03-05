<?php

namespace vfs\storage\interface;

interface IStorageProvider
{
    public function getProviderName(): string;
    public function get(bool $must = false): string|null;
    public function save(string $path): bool;
    public function delete(): bool;
    public function exists(): bool;
    public function getFullPath(): string|null;
    public function move(string $path): bool;
    public function copy(string $path): bool;
    public function rename(string $path): bool;
    public function createDirectory(string $path): bool;
}