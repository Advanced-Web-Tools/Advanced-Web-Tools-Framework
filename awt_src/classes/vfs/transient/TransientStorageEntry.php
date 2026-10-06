<?php

namespace vfs\transient;

use vfs\transient\enums\ETransientType;
use vfs\transient\interfaces\ITransientStorageEntry;

class TransientStorageEntry implements ITransientStorageEntry
{
    public string $name;
    public string $path;
    public string|array|null $content = null;
    public int $lastModified = 0;
    public ETransientType $type = ETransientType::FILE;
    public int $size = 0;

    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = $path;

        $this->refresh();
    }

    public function refresh(): self
    {
        clearstatcache(true, $this->path);
        if (!is_file($this->path)) {
            $this->size = 0;
            $this->lastModified = 0;
            $this->content = null;
            return $this;
        }

        $this->type = ETransientType::fromPath($this->path);
        $this->size = filesize($this->path);
        $this->lastModified = filemtime($this->path);

        return $this;
    }

    public function loadContent(): self
    {
        $this->refresh();
        if (!is_file($this->path)) return $this;

        if (
            $this->type === ETransientType::FILE ||
            $this->type === ETransientType::TXT ||
            $this->type === ETransientType::HTML ||
            $this->type === ETransientType::JSON ||
            $this->type === ETransientType::XML ||
            $this->type === ETransientType::JS
        ) {
            $content = @file_get_contents($this->path);
            if ($content === false) throw new \RuntimeException("Cannot read transient file: {$this->path}");
            $this->content = $content;
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
        $data = is_array($content)
            ? (ETransientType::fromPath($this->path) === ETransientType::JSON
                ? json_encode($content, JSON_THROW_ON_ERROR) : "<?php return " . var_export($content, true) . ";")
            : $content;
        $directory = dirname($this->path);
        $temporary = $directory . DIRECTORY_SEPARATOR . '.write-' . bin2hex(random_bytes(16));
        $handle = @fopen($temporary, 'xb');
        if ($handle === false) throw new \RuntimeException("Cannot create temporary file in {$directory}");
        try {
            $offset = 0;
            $length = strlen($data);
            while ($offset < $length) {
                $written = @fwrite($handle, substr($data, $offset));
                if ($written === false || $written === 0) throw new \RuntimeException("Cannot write file: {$this->path}");
                $offset += $written;
            }
            if (!@fflush($handle)) throw new \RuntimeException("Cannot flush file: {$this->path}");
            fclose($handle);
            $handle = null;
            if (!@rename($temporary, $this->path)) throw new \RuntimeException("Cannot replace file: {$this->path}");
            if (function_exists('opcache_invalidate')) opcache_invalidate($this->path, true);
        } finally {
            if (is_resource($handle)) fclose($handle);
            if (is_file($temporary)) unlink($temporary);
        }
        $this->content = $content;

        return $this->refresh();
    }

    public function delete(): bool
    {
        clearstatcache(true, $this->path);
        $deleted = (file_exists($this->path) || is_link($this->path)) && @unlink($this->path);
        if ($deleted && function_exists('opcache_invalidate')) opcache_invalidate($this->path, true);
        return $deleted;
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