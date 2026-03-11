<?php

namespace vfs\storage;

use model\Model;
use vfs\storage\enums\EOwnerType;

/**
 * Class StorageEntry
 *
 * A pure data-model/entity (SRP). All file system concerns have been
 * removed and now live in LocalFileSystemService. This class is only
 * responsible for holding and validating storage entry state.
 */
class StorageEntry extends Model
{
    public ?int        $id;
    public ?string     $name;
    public ?string     $path;
    public ?string     $url;
    public ?int        $size;
    public ?string     $middleware;
    public ?int        $lastModified;
    public ?int        $ownerId;
    public string|EOwnerType $ownerType;

    public function __construct(?int $id = null)
    {
        parent::__construct();

        $this->id = $id;
        $this->model_source = 'awt_storage';
        if ($this->id !== null) {
            $this->selectByID($this->id);
        }
    }

    // ----------------------------------------------------------------
    // Fluent setters
    // ----------------------------------------------------------------

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @param int $size Pre-computed file size in bytes.
     */
    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @param int $lastModified Unix timestamp of the last modification.
     */
    public function setLastModified(int $lastModified): self
    {
        $this->lastModified = $lastModified;
        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Provide an explicit URL, or pass null to derive it from id + name.
     */
    public function setUrl(?string $url = null): self
    {
        $this->url = $url ?? '/storage/' . $this->id . '/' . $this->name;
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
        $this->ownerType = $ownerType ?? EOwnerType::USER;
        return $this;
    }

    // ----------------------------------------------------------------
    // Getters
    // ----------------------------------------------------------------

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function save(): bool
    {
        $this->ownerType = $this->ownerType->value ?? EOwnerType::USER->value;
        return parent::save();
    }

    public function saveModel(): ?int
    {
        $this->ownerType = $this->ownerType->value ?? EOwnerType::USER->value;
        return parent::saveModel();
    }
}
