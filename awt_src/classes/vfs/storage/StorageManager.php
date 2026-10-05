<?php

namespace vfs\storage;

use context\events\GetContextEvent;
use vfs\storage\interfaces\IFileSystemService;
use vfs\storage\interfaces\IOwnerStorageStrategy;
use vfs\storage\interfaces\IStorageManager;
use vfs\storage\interfaces\IStorageRepository;

class StorageManager implements IStorageManager
{
    private array $strategies = [];

    public function __construct(
        private readonly IStorageRepository $repository,
        private readonly IFileSystemService $fileSystem,
        array $strategies = []
    ) {
        foreach ($strategies as $strategy) $this->registerStrategy($strategy);
    }

    public function registerStrategy(IOwnerStorageStrategy $strategy): void
    {
        $this->strategies[$strategy->supports()->value] = $strategy;
    }

    public function get(int $id): StorageEntry { return $this->repository->fetchById($id); }

    public function move(int $id, string $location): bool
    {
        $entry = $this->get($id);
        if (!$this->fileSystem->makeDirectory($location)) return false;
        return $this->relocate($entry, FileName::unique($location, $entry->getPath()));
    }

    public function rename(int $id, string $name): bool
    {
        $entry = $this->get($id);
        return $this->relocate($entry, FileName::unique(dirname($entry->getPath()), $entry->getPath()), $name);
    }

    private function relocate(StorageEntry $entry, string $destination, ?string $name = null): bool
    {
        $oldPath = $entry->getPath();
        $oldName = $entry->getName();
        $oldUrl = $entry->url;
        if (!$this->fileSystem->move($oldPath, $destination)) return false;
        try {
            $entry->setPath($destination);
            if ($name !== null) {
                $entry->setName($name);
                if ($oldUrl === null || $oldUrl === '/storage/' . $entry->id . '/' . rawurlencode($oldName)
                    || $oldUrl === '/storage/' . $entry->id . '/' . $oldName) $entry->setUrl();
            }
            if ($this->repository->update($entry)) return true;
        } catch (\Throwable $e) {
            $this->restore($destination, $oldPath);
            $entry->setPath($oldPath)->setName($oldName);
            $entry->url = $oldUrl;
            throw $e;
        }
        $this->restore($destination, $oldPath);
        $entry->setPath($oldPath)->setName($oldName);
        $entry->url = $oldUrl;
        return false;
    }

    private function restore(string $source, string $destination): void
    {
        if (!$this->fileSystem->move($source, $destination)) {
            throw new \RuntimeException("Could not restore file to {$destination}; file remains at {$source}.");
        }
    }

    public function delete(int $id): bool
    {
        $entry = $this->get($id);
        $path = $entry->getPath();
        $backup = FileName::unique(dirname($path), $path);
        if (!$this->fileSystem->move($path, $backup)) return false;
        try {
            $deleted = $this->repository->delete($entry);
        } catch (\Throwable $e) {
            $this->restore($backup, $path);
            throw $e;
        }
        if (!$deleted) { $this->restore($backup, $path); return false; }
        if (!$this->fileSystem->delete($backup)) {
            throw new \RuntimeException("Storage record deleted, but file cleanup failed: {$backup}");
        }
        return true;
    }

    public function copy(int $id): StorageEntry
    {
        $entry = $this->get($id);
        $path = FileName::unique(dirname($entry->getPath()), $entry->getPath());
        if (!$this->fileSystem->copy($entry->getPath(), $path)) throw new \RuntimeException('Could not copy storage file.');
        try {
            $copy = (new StorageEntry())->setPath($path)->setName('copy_' . $entry->getName())
                ->setSize($this->fileSystem->fileSize($path))->setLastModified($this->fileSystem->lastModified($path))
                ->setOwnerType($entry->getOwnerType())->setOwnerId($entry->getOwnerId())
                ->setMiddleware($entry->getMiddleware());
            return $this->repository->create($copy);
        } catch (\Throwable $e) {
            if (!$this->fileSystem->delete($path)) throw new \RuntimeException("Could not clean up failed copy: {$path}", previous: $e);
            throw $e;
        }
    }

    public function registerLocal(StorageEntry $entry, ?string $ownerName = null): bool
    {
        $ownerType = $entry->getOwnerType();
        if ($ownerType === null || !isset($this->strategies[$ownerType->value])) {
            throw new \RuntimeException('No storage strategy registered for owner type: ' . ($ownerType?->value ?? 'null'));
        }
        if ($ownerName === null) {
            global $eventDispatcher;
            if (!isset($eventDispatcher)) throw new \LogicException('Supply an owner name when no context dispatcher is available.');
            $event = new GetContextEvent();
            $eventDispatcher->dispatch($event);
            $context = $event->getContext();
            $ownerName = $context->contextName;
            if ($entry->getOwnerId() === null) $entry->setOwnerId($context->contextId);
        }
        return $this->strategies[$ownerType->value]->register($entry, $ownerName);
    }
}
