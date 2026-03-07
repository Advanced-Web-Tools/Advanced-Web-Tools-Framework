<?php

namespace vfs\storage\entry;

use model\Model;
use vfs\storage\entry\enums\EStorageEntryType;
use vfs\storage\entry\enums\EStorageOwnerType;
use vfs\storage\entry\middleware\IStorageEntryMiddleware;
use vfs\storage\providers\interface\IStorageProvider;

class StorageEntry extends Model
{
    public string $name;
    public EStorageEntryType $type;
    public EStorageOwnerType $ownerType;
    public string $provider;
    public ?string $middleware;
    public ?string $ownerName = null;
    public ?string $systemPath = null;
    public ?int $packageUploaderId = null;

    private ?IStorageProvider $providerObject = null;
    private ?IStorageEntryMiddleware $middlewareObject = null;

    public function __construct(?int $id = null)
    {
        parent::__construct();
        if($id !== null)
            $this->selectByID($id);
    }

    public function setMiddleware(IStorageEntryMiddleware $middleware): self
    {
        $this->middleware = $middleware::class;
        return $this;
    }

    public function setOwnerType(EStorageOwnerType $ownerType): self
    {
        $this->ownerType = $ownerType;
        return $this;
    }

    public function setProvider(IStorageProvider $provider): self
    {
        $this->provider = $provider::class;
        return $this;
    }

    public function setOwnerName(?string $name = null): self
    {
        $this->ownerName = $name;
        return $this;
    }

    public function setPackageUploaderId(?int $packageUploaderId = null): self
    {
        $this->packageUploaderId = $packageUploaderId;
        return $this;
    }

    public function setSystemPath(?string $systemPath = null): self
    {
        $this->systemPath = $systemPath;
        return $this;
    }


    public function saveEntry(): bool
    {
        $entry = $this->initProviderObject()->providerObject->save($this);
        if($entry !== null) {
            $this->copyFrom($entry);
            return $this->saveModel();
        }
        return false;
    }

    public function deleteEntry(): bool
    {
        $res = $this->initProviderObject()->providerObject->delete($this);
        if($res)
            $res = $this->deleteModel();
        return $res;
    }

    public function rename(string $newName): bool
    {
        $newEntry = $this->initProviderObject()->providerObject->rename($this, $newName);

        if ($newEntry !== null) {
            $this->copyFrom($newEntry);
            return $this->save();
        }

        return false;
    }

    public function move(string $newPath): bool
    {
        $newEntry = $this->initProviderObject()->providerObject->move($this, $newPath);
        if ($newEntry !== null) {
            $this->copyFrom($newEntry);
            return $this->save();
        }
        return false;
    }

    public function copy(string $location): ?StorageEntry
    {
        return $this->initProviderObject()->providerObject->copy($this, $location);
    }

    public function getUrl(): ?string
    {
        return $this->initProviderObject()->providerObject->buildLocation($this);
    }

    public function getPath(): ?string
    {
        return $this->initProviderObject()->providerObject->buildLocation($this, true);
    }

    private function initProviderObject(): self
    {
        $this->providerObject = new $this->provider;
        return $this;
    }

    private function initMiddlewareObject(): self
    {
        if($this->middleware !== null)
            $this->middlewareObject = new $this->middleware;
        return $this;
    }

    private function copyFrom(self $entry): void
    {
        foreach (get_object_vars($entry) as $property => $value) {
            $this->$property = $value;
        }
    }
}