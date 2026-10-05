<?php

namespace vfs\transient;

use vfs\transient\enums\ETransientType;
use vfs\transient\interfaces\ITransientStorageEntry;

class TransientStorageEntry implements ITransientStorageEntry
{
    public string $name;
    public string $path;
    public string|array|null $content = null;
    public int $lastModified;
    public ETransientType $type;
    public int $size;

    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;

        $this->refresh();
    }

    public function refresh(): self
    {
        if (!file_exists($this->path)) {
            return $this;
        }

        $this->type = ETransientType::fromPath($this->path);
        $this->size = filesize($this->path);
        $this->lastModified = filemtime($this->path);

        return $this;
    }

    public function loadContent(): self
    {
        if (!file_exists($this->path)) {
            return $this;
        }

        if (
            $this->type === ETransientType::FILE ||
            $this->type === ETransientType::TXT ||
            $this->type === ETransientType::HTML ||
            $this->type === ETransientType::JSON ||
            $this->type === ETransientType::XML ||
            $this->type === ETransientType::JS
        ) {
            $this->content = file_get_contents($this->path);
            return $this;
        }

        if (
            $this->type === ETransientType::CACHE ||
            $this->type === ETransientType::PHP
        ) {
            $this->content = include $this->path;
        }

        return $this;
    }

    public function write(string|array $content): self
    {
        if (is_array($content)) {
            file_put_contents($this->path, "<?php return " . var_export($content, true) . ";");
        } else {
            file_put_contents($this->path, $content);
        }

        return $this->refresh();
    }

    public function delete(): bool
    {
        return file_exists($this->path) && unlink($this->path);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getLastModified(): int
    {
        return $this->lastModified;
    }

    public function __toString(): string
    {
        return $this->path;
    }
}