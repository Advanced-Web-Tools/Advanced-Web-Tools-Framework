<?php

namespace vfs\storage;

use model\Model;
use vfs\storage\enums\EOwnerType;

class StorageEntry extends Model
{
    public ?int $id;
    public ?string $name;
    public ?string $path;
    public ?string $url;
    public ?int $size;
    public ?string $middleware;
    public ?int $lastModified;
    public ?int $ownerId;
    public ?EOwnerType $ownerType;
    public function __construct(?int $id = null) {
        parent::__construct();

        $this->id = $id;

        if($this->id !== null)
            $this->selectByID($this->id, "awt_storage");
    }


    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function setSize(?int $size = null): self
    {
        if($size === null)
            $size = filesize($this->path);

        $this->size = $size;
        return $this;
    }

    public function setLastModified(?int $lastModified = null): self
    {
        if($lastModified === null)
            $lastModified = filemtime($this->path);

        $this->lastModified = $lastModified;
        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setUrl(?string $url = null): self
    {
        $this->url = $url;
        if($url === null)
            $this->url = "/storage/" . $this->id . "/" . $this->name;

        return $this;
    }


    public function setMiddleware(?string $middleware = null): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function setOwnerId(?int $ownerId = null): self
    {
        $this->ownerId = $ownerId;
        return $this;
    }

    public function setOwnerType(?EOwnerType $ownerType = null): self
    {
        if($ownerType === null)
            $ownerType = EOwnerType::USER;

        $this->ownerType = $ownerType;
        return $this;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function getOwnerType(): ?EOwnerType
    {
        return $this->ownerType;
    }

    public function getMiddleware(): ?string
    {
        return $this->middleware;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getLastModified(): int
    {
        return $this->lastModified;
    }
}