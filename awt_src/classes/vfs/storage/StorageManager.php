<?php

namespace vfs\storage;

use context\events\GetContextEvent;
use vfs\storage\interfaces\IFileSystemService;
use vfs\storage\interfaces\IOwnerStorageStrategy;
use vfs\storage\interfaces\IStorageManager;
use vfs\storage\interfaces\IStorageRepository;

/**
 * Class StorageManager
 *
 * Orchestrates storage operations (SRP) by delegating:
 *   - File I/O      → IFileSystemService      (DIP)
 *   - Persistence   → IStorageRepository      (DIP)
 *   - Owner routing → IOwnerStorageStrategy[] (OCP)
 *
 * All dependencies are injected, making the class fully testable and
 * swappable. Static methods have been replaced with instance methods
 * so the class can properly participate in dependency injection.
 */
class StorageManager implements IStorageManager
{
    /** @var IOwnerStorageStrategy[] keyed by EOwnerType value */
    private array $strategies = [];

    /**
     * @param IStorageRepository    $repository
     * @param IFileSystemService    $fileSystem
     * @param IOwnerStorageStrategy[] $strategies
     */
    public function __construct(
        private readonly IStorageRepository $repository,
        private readonly IFileSystemService $fileSystem,
        array $strategies = []
    ) {
        foreach ($strategies as $strategy) {
            $this->registerStrategy($strategy);
        }
    }

    /**
     * Register an owner-type strategy (OCP — extend without modifying).
     */
    public function registerStrategy(IOwnerStorageStrategy $strategy): void
    {
        $this->strategies[$strategy->supports()->value] = $strategy;
    }

    // ----------------------------------------------------------------
    // IStorageManager implementation
    // ----------------------------------------------------------------

    public function get(int $id): StorageEntry
    {
        return $this->repository->fetchById($id);
    }

    /**
     * Move a file to a new directory and update its stored path.
     */
    public function move(int $id, string $location): bool
    {
        $entry       = $this->repository->fetchById($id);
        $extension   = pathinfo($entry->getPath(), PATHINFO_EXTENSION);
        $hashedName  = hash('SHA256', $entry->getName()) . '.' . $extension;
        $destination = rtrim($location, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hashedName;

        $this->fileSystem->move($entry->getPath(), $destination);

        $entry->setPath($destination);

        return $this->repository->update($entry);
    }

    /**
     * Delete the physical file and its database record.
     */
    public function delete(int $id): bool
    {
        $entry = $this->repository->fetchById($id);

        if (!$this->fileSystem->delete($entry->getPath())) {
            return false;
        }

        return $this->repository->delete($entry);
    }

    /**
     * Rename a file on disk and update the stored name and path.
     *
     * Bug fix: the original implementation had the str_replace arguments
     * in the wrong order, producing an incorrect base path.
     */
    public function rename(int $id, string $name): bool
    {
        $entry     = $this->repository->fetchById($id);
        $oldPath   = $entry->getPath();
        $basePath  = dirname($oldPath) . DIRECTORY_SEPARATOR;
        $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
        $newName   = hash('SHA512', $name) . '.' . $extension;
        $newPath   = $basePath . $newName;

        $this->fileSystem->rename($oldPath, $newPath);

        $entry->setPath($newPath);
        $entry->setName($name);

        return $this->repository->update($entry);
    }

    /**
     * Duplicate a file on disk and persist a new StorageEntry for it.
     *
     * Bug fix: the original implementation had the str_replace arguments
     * in the wrong order, producing an incorrect copy path.
     */
    public function copy(int $id): StorageEntry
    {
        $entry     = $this->repository->fetchById($id);
        $extension = pathinfo($entry->getPath(), PATHINFO_EXTENSION);
        $copyName  = 'copy_' . $entry->getName();
        $newFile   = hash('SHA512', $copyName) . '.' . $extension;
        $newPath   = dirname($entry->getPath()) . DIRECTORY_SEPARATOR . $newFile;

        $this->fileSystem->copy($entry->getPath(), $newPath);

        $newEntry = new StorageEntry();
        $newEntry->setPath($newPath)
                 ->setName($copyName)
                 ->setSize($this->fileSystem->fileSize($newPath))
                 ->setLastModified($this->fileSystem->lastModified($newPath))
                 ->setUrl()
                 ->setOwnerType($entry->getOwnerType())
                 ->setOwnerId($entry->getOwnerId());

        return $this->repository->create($newEntry);
    }

    /**
     * Register a local file using the strategy that matches the entry's owner type.
     *
     * OCP: supporting a new EOwnerType requires only registering a new
     * IOwnerStorageStrategy — this method never needs to change.
     *
     * @throws \RuntimeException if no strategy is registered for the owner type.
     */
    public function registerLocal(StorageEntry $entry, ?string $ownerName = null): bool
    {
        $ownerType = $entry->getOwnerType();

        if($ownerName === null) {
            global $eventDispatcher;
            $contextEvent = new GetContextEvent();
            $eventDispatcher->dispatch($contextEvent);
            $ownerName = $contextEvent->getContext()->contextName;
            $entry->setOwnerId($contextEvent->getContext()->contextId);
        }

        if ($ownerType === null || !isset($this->strategies[$ownerType->value])) {
            throw new \RuntimeException(
                "No storage strategy registered for owner type: " . ($ownerType?->value ?? 'null')
            );
        }

        return $this->strategies[$ownerType->value]->register($entry, $ownerName);
    }
}
