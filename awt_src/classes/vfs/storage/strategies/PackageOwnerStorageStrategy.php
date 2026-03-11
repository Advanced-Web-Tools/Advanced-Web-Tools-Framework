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
        $extension   = pathinfo($entry->getPath(), PATHINFO_EXTENSION);
        $packageDir  = $this->storageBasePath
            . self::PACKAGES_DIR
            . DIRECTORY_SEPARATOR
            . $ownerName
            . DIRECTORY_SEPARATOR;

        $this->fileSystem->makeDirectory($packageDir);

        $hashedName  = hash('SHA256', $entry->getName()) . '.' . $extension;
        $destination = $packageDir . $hashedName;

        $this->fileSystem->move($entry->getPath(), $destination);

        $entry->setPath($destination);
        $entry->setUrl();
        $entry->setSize($this->fileSystem->fileSize($destination));
        $entry->setLastModified($this->fileSystem->lastModified($destination));

        if($entry->getOwnerId() === null) {
            $db = new DatabaseManager();
            $entry->setOwnerId($db->table('awt_package')->select(['id'])->where(['name' => $ownerName])->get()[0]['id']);
        }

        $created = $this->repository->create($entry);

        return $created->id !== null;
    }
}
