<?php

namespace uninstaller\cleanup;

use uninstaller\interfaces\IPackageCleanup;
use vfs\storage\enums\EOwnerType;
use vfs\storage\interfaces\IFileSystemService;
use vfs\storage\interfaces\IStorageRepository;

class StorageCleanup implements IPackageCleanup
{
    public function __construct(
        private readonly IStorageRepository $repository,
        private readonly IFileSystemService $fileSystem,
    ) {}

    public function clean(int $id, string $name): array
    {
        $errors = [];
        foreach ($this->repository->fetchByOwnerTypeAndOwner(EOwnerType::PACKAGE, $id) as $entry) {
            try {
                $path = $entry->getPath();
                if ($this->fileSystem->exists($path) && !$this->fileSystem->delete($path)) {
                    throw new \RuntimeException("Failed to delete storage file: {$path}");
                }
                // Keep the record on file deletion failure so cleanup can be retried.
                if (!$this->repository->delete($entry)) {
                    throw new \RuntimeException('Failed to purge storage record.');
                }
            } catch (\Throwable $error) {
                $errors[] = 'Storage entry ' . $entry->getId() . ': ' . $error->getMessage();
            }
        }
        return $errors;
    }
}
