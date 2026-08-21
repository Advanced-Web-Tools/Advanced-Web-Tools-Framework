<?php

namespace vfs\cache;

use vfs\transient\interfaces\ITransientStorageEntry;

class CacheEntry
{
    private string $name;
    private array $content;
    private ITransientStorageEntry $entry;

    public function __construct(string $name, array $content, ITransientStorageEntry $entry)
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

    public function getStorageEntry(): ITransientStorageEntry
    {
        return $this->entry;
    }

    public function getLastModified(): int
    {
        return $this->entry->getLastModified();
    }
}