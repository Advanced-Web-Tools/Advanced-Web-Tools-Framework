<?php

namespace installer\package;

use installer\interfaces\package\IPackageStorageTreeGenerator;
use vfs\storage\interfaces\IFileSystemService;
use vfs\storage\interfaces\IStorageRepository;
use vfs\storage\StorageEntry;
use vfs\storage\enums\EOwnerType;

class PackageStorageTreeGenerator implements IPackageStorageTreeGenerator
{
    private array $storageTree;
    private array $entries;

    public function __construct(
        private readonly IFileSystemService $fsService,
        private readonly IStorageRepository $repository,
        private string $source = '',
        private string $baseUrl = '',
        private int $packageId = 0,
    ) {
        $this->storageTree = ['directories' => [], 'files' => []];
        $this->entries = [];
    }

    /**
     * @inheritDoc
     */
    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    /**
     * @inheritDoc
     */
    public function setPackageId(int $packageId): void
    {
        $this->packageId = $packageId;
    }

    /**
     * @inheritDoc
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @inheritDoc
     */
    public function buildStorageTree(): IPackageStorageTreeGenerator
    {
        $this->storageTree = ['directories' => [], 'files' => []];

        if (is_dir($this->source)) {
            $this->scanDirectory($this->source);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function generate(): IPackageStorageTreeGenerator
    {
        $this->entries = [];
        foreach ($this->storageTree['files'] as $relPath) {
            $path = $this->source . DIRECTORY_SEPARATOR . $relPath;

            $entry = new StorageEntry();
            $entry->setName(basename($relPath))
                  ->setPath($path)
                  ->setUrl($this->baseUrl . '/' . implode('/', array_map('rawurlencode', explode(DIRECTORY_SEPARATOR, $relPath))))
                  ->setSize($this->fsService->fileSize($path))
                  ->setLastModified($this->fsService->lastModified($path))
                  ->setOwnerType(EOwnerType::PACKAGE)
                  ->setOwnerId($this->packageId);

            $this->entries[] = $entry;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStorageTree(): array
    {
        return $this->storageTree;
    }

    /**
     * @inheritDoc
     */
    public function registerItems(): bool
    {
        $existing = [];
        foreach ($this->repository->fetchByOwnerTypeAndOwner(EOwnerType::PACKAGE, $this->packageId) as $entry) {
            $existing[$entry->getPath()] = $entry;
        }
        foreach ($this->entries as $entry) {
            $previous = $existing[$entry->getPath()] ?? null;
            if ($previous === null) {
                $this->repository->create($entry);
            } else {
                $entry->id = $previous->id;
                $entry->setModelId($previous->id);
                $changed = false;
                foreach (['name', 'path', 'url', 'size', 'middleware', 'lastModified', 'ownerId', 'ownerType'] as $field) {
                    $before = $previous->$field;
                    $after = $entry->$field;
                    if ($before instanceof EOwnerType) $before = $before->value;
                    if ($after instanceof EOwnerType) $after = $after->value;
                    if ($before !== $after) { $changed = true; break; }
                }
                if ($changed && !$this->repository->update($entry)) throw new \RuntimeException('Failed to update package asset registration.');
            }
        }

        return true;
    }

    private function scanDirectory(string $dir): void
    {
        $scan = scandir($dir);
        unset($scan[0], $scan[1]);

        foreach ($scan as $item) {
            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
            $relPath  = substr($fullPath, strlen($this->source) + 1);

            if (is_dir($fullPath)) {
                $this->storageTree['directories'][] = $relPath;
                $this->scanDirectory($fullPath);
            } else {
                $this->storageTree['files'][] = $relPath;
            }
        }
    }
}
