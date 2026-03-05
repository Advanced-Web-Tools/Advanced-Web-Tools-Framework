<?php

namespace vfs\storage\providers;

use vfs\storage\interface\IStorageProvider;

class LocalStorageProvider implements IStorageProvider
{

    private string $root;
    private string $cache;
    private string $temp;
    private string $public;
    private string $uploads;
    private string $packages;
    private string $config;


    public function __construct() {
        $this->root = DATA . "storage" . DIRECTORY_SEPARATOR;
        $this->cache = $this->root . "cache" . DIRECTORY_SEPARATOR;
        $this->temp = $this->root . "temp" . DIRECTORY_SEPARATOR;
        $this->public = $this->root . "public" . DIRECTORY_SEPARATOR;
        $this->uploads = $this->root . "uploads" . DIRECTORY_SEPARATOR;
        $this->config = $this->root . "config" . DIRECTORY_SEPARATOR;
    }

    public function getProviderName(): string
    {
        return "local";
    }

    public function get(bool $must = false): string|null
    {
        // TODO: Implement get() method.
    }

    public function save(string $path): bool
    {
        // TODO: Implement save() method.
    }

    public function delete(): bool
    {
        // TODO: Implement delete() method.
    }

    public function exists(): bool
    {
        // TODO: Implement exists() method.
    }

    public function getFullPath(): string|null
    {
        // TODO: Implement getFullPath() method.
    }

    public function move(string $path): bool
    {
        // TODO: Implement move() method.
    }

    public function copy(string $path): bool
    {
        // TODO: Implement copy() method.
    }

    public function rename(string $path): bool
    {
        // TODO: Implement rename() method.
    }

    public function createDirectory(string $path): bool
    {
        // TODO: Implement createDirectory() method.
    }
}