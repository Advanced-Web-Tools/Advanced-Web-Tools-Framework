<?php

namespace vfs\cache;

use vfs\transient\TransientStorageEntry;

class CacheEntry
{
    private string $name;
    private array $content;
    private TransientStorageEntry $entry;

    public function __construct(string $name, array $content, TransientStorageEntry $entry)
    {
        $this->name = $name;
        $this->content = $content;
        $this->entry = $entry;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContent(): array
    {
        return $this->content;
    }

    public function getStorageEntry(): TransientStorageEntry
    {
        return $this->entry;
    }

    public function getLastModified(): int
    {
        return $this->entry->lastModified;
    }
}