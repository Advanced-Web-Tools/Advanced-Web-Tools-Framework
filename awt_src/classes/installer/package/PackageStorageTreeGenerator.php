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
        private string $destination = '',
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
    public function setDestination(string $destination): void
    {
        $this->destination = $destination;
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
        if (!$this->fsService->makeDirectory($this->destination)) throw new \RuntimeException('Could not create package storage directory.');

        foreach ($this->storageTree['directories'] as $dir) {
            if (!$this->fsService->makeDirectory($this->destination . DIRECTORY_SEPARATOR . $dir)) throw new \RuntimeException('Could not create package asset directory.');
        }

        foreach ($this->storageTree['files'] as $relPath) {
            $srcPath  = $this->source . DIRECTORY_SEPARATOR . $relPath;
            $destPath = $this->destination . DIRECTORY_SEPARATOR . $relPath;

            $this->replaceAsset($srcPath, $destPath);

            $entry = new StorageEntry();
            $entry->setName(basename($relPath))
                  ->setPath($destPath)
                  ->setUrl($this->baseUrl . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relPath))
                  ->setSize($this->fsService->fileSize($destPath))
                  ->setLastModified($this->fsService->lastModified($destPath))
                  ->setOwnerType(EOwnerType::PACKAGE)
                  ->setOwnerId($this->packageId);

            $this->entries[] = $entry;
        }

        return $this;
    }

    private function replaceAsset(string $source, string $destination): void
    {
        $staged = \vfs\storage\FileName::unique(dirname($destination), $destination);
        if (!$this->fsService->copy($source, $staged)) throw new \RuntimeException("Could not copy package asset: {$source}");
        $backup = null;
        try {
            if ($this->fsService->exists($destination)) {
                $backup = \vfs\storage\FileName::unique(dirname($destination), $destination);
                if (!$this->fsService->move($destination, $backup)) throw new \RuntimeException("Could not back up package asset: {$destination}");
            }
            if (!$this->fsService->move($staged, $destination)) {
                if ($backup !== null && !$this->fsService->move($backup, $destination)) {
                    throw new \RuntimeException("Could not restore package asset; backup remains at {$backup}");
                }
                throw new \RuntimeException("Could not replace package asset: {$destination}");
            }
            if ($backup !== null && !$this->fsService->delete($backup)) throw new \RuntimeException("Could not clean up package asset backup: {$backup}");
        } finally {
            if ($this->fsService->exists($staged) && !$this->fsService->delete($staged)) {
                throw new \RuntimeException("Could not clean up staged package asset: {$staged}");
            }
        }
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
        foreach ($this->entries as $entry) {
            $this->repository->create($entry);
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
