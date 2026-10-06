<?php

namespace vfs\storage\strategies;

use database\DatabaseManager;
use vfs\storage\interfaces\IFileSystemService;
use vfs\storage\interfaces\IOwnerStorageStrategy;
use vfs\storage\interfaces\IStorageRepository;
use vfs\storage\enums\EOwnerType;
use vfs\storage\StorageEntry;

/**
 * Class PackageOwnerStorageStrategy
 *
 * Handles file registration for the PACKAGE owner type (OCP).
 * Adding new EOwnerType strategies never requires touching StorageManager.
 */
class PackageOwnerStorageStrategy implements IOwnerStorageStrategy
{
    private const PACKAGES_DIR = 'packages';

    public function __construct(
        private readonly IFileSystemService $fileSystem,
        private readonly IStorageRepository $repository,
        private readonly string             $storageBasePath
    ) {}

    public function supports(): EOwnerType
    {
        return EOwnerType::PACKAGE;
    }

    public function register(StorageEntry $entry, string $ownerName): bool
    {
        if ($ownerName === '' || $ownerName === '.' || $ownerName === '..' || preg_match('/[\\\\\/\x00]/', $ownerName)) {
            throw new \InvalidArgumentException('Owner name must be a single directory name.');
        }
        if ($entry->getOwnerId() === null) {
            $rows = (new DatabaseManager())->table('awt_package')->select(['id'])->where(['name' => $ownerName])->get(1);
            if ($rows === []) throw new \OutOfBoundsException("Package not found: {$ownerName}");
            $entry->setOwnerId($rows[0]['id']);
        }
        $directory = rtrim($this->storageBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . self::PACKAGES_DIR . DIRECTORY_SEPARATOR . $ownerName;
        if (!$this->fileSystem->makeDirectory($directory)) throw new \RuntimeException("Could not create directory: {$directory}");
        $oldPath = $entry->getPath();
        $oldUrl = $entry->url;
        $destination = \vfs\storage\FileName::unique($directory, $oldPath);
        if (!$this->fileSystem->move($oldPath, $destination)) throw new \RuntimeException('Could not move storage file.');
        try {
            $entry->setPath($destination)->setSize($this->fileSystem->fileSize($destination))
                ->setLastModified($this->fileSystem->lastModified($destination));
            $created = $this->repository->create($entry);
            if ($created->id === null) throw new \RuntimeException('Failed to register storage entry.');
            return true;
        } catch (\Throwable $e) {
            if (!$this->fileSystem->move($destination, $oldPath)) {
                throw new \RuntimeException("Could not restore failed registration; file remains at {$destination}.", previous: $e);
            }
            $entry->setPath($oldPath);
            $entry->url = $oldUrl;
            throw $e;
        }
    }
}
