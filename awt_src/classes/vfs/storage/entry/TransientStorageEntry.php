<?php

namespace vfs\storage\entry;

use vfs\storage\entry\enums\EStorageEntryType;

class TransientStorageEntry
{
    public string $name;
    public EStorageEntryType $type;
    public string $path;
    public ?string $extension = null;
    public ?string $content = null;
    public ?string $uploadPath = null;

    public function __construct() {}

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setType(EStorageEntryType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): EStorageEntryType
    {
        return $this->type;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;
        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setUploadPath(string $uploadPath): self
    {
        $this->uploadPath = $uploadPath;
        return $this;
    }

    public function getUploadPath(): ?string
    {
        return $this->uploadPath;
    }

    public function getFullName(): string
    {
        return $this->extension
            ? $this->name . '.' . $this->extension
            : $this->name;
    }
}