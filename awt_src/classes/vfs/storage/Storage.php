<?php

namespace vfs\storage;

use vfs\storage\enums\EOwnerType;
use vfs\storage\interfaces\IFileSystemService;
use vfs\storage\interfaces\IStorageFacade;
use vfs\storage\interfaces\IStorageManager;
use vfs\storage\services\LocalFileSystemService;
use vfs\storage\strategies\PackageOwnerStorageStrategy;

/**
 * Class Storage
 *
 * Self-wiring facade for all storage operations (upload, multi-upload, download).
 *
 * By default the class builds and wires its own dependency graph, so callers
 * need only supply a base path:
 *
 *   $storage = new Storage('/var/www/storage/');
 *   $storage->upload($_FILES['file'], EOwnerType::PACKAGE, ownerId: 1);
 *
 * Every dependency can be overridden via constructor parameters for testing
 * or alternative implementations — no manual wiring required in production.
 */
class Storage implements IStorageFacade
{
    private const STAGING_DIR = 'staging' . DIRECTORY_SEPARATOR;

    private IStorageManager   $manager;
    private IFileSystemService $fileSystem;

    /**
     * @param string                  $basePath    Absolute path to the storage root.
     * @param IStorageManager|null    $manager     Override for testing / custom implementations.
     * @param IFileSystemService|null $fileSystem  Override for testing / custom implementations.
     */
    public function __construct(
        private readonly string $basePath    = DATA . 'storage' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR,
        ?IStorageManager        $manager     = null,
        ?IFileSystemService     $fileSystem  = null
    ) {
        $this->fileSystem = $fileSystem ?? new LocalFileSystemService();
        $this->manager    = $manager    ?? $this->buildDefaultManager();
    }

    // ----------------------------------------------------------------
    // IStorageFacade implementation
    // ----------------------------------------------------------------

    public function upload(
        array      $file,
        EOwnerType $ownerType,
        ?int       $ownerId    = null,
        ?string    $ownerName  = null,
        ?string    $middleware = null
    ): StorageEntry {
        $this->assertValidFileArray($file);

        $stagingPath = $this->stageUploadedFile($file);

        $entry = $this->buildEntry($file['name'], $stagingPath, $ownerType, $ownerId, $middleware);

        $this->manager->registerLocal($entry, $ownerName);

        return $entry;
    }

    public function uploadMultiple(
        array      $files,
        EOwnerType $ownerType,
        ?int       $ownerId    = null,
        ?string    $ownerName  = null,
        ?string    $middleware = null
    ): array {
        $entries = [];

        foreach ($files as $index => $file) {
            try {
                $entries[] = $this->upload($file, $ownerType, $ownerId, $ownerName, $middleware);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    "Failed to upload file at index {$index} ('{$file['name']}'): " . $e->getMessage(),
                    previous: $e
                );
            }
        }

        return $entries;
    }

    public function download(int $id, string $disposition = 'attachment'): void
    {
        $entry = $this->manager->get($id);
        $path  = $entry->getPath();

        if (!$this->fileSystem->exists($path)) {
            throw new \RuntimeException(
                "File not found on disk for storage entry {$id}: {$path}"
            );
        }

        $this->sendDownloadHeaders($entry, $disposition);

        readfile($path);
        exit;
    }

    public function normaliseMultiFileArray(array $filesEntry): array
    {
        $keys   = ['name', 'tmp_name', 'error', 'size', 'type'];
        $count  = count($filesEntry['name'] ?? []);
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $single = [];
            foreach ($keys as $key) {
                $single[$key] = $filesEntry[$key][$i] ?? null;
            }
            $result[] = $single;
        }

        return $result;
    }

    // ----------------------------------------------------------------
    // Private — dependency construction
    // ----------------------------------------------------------------

    /**
     * Build the default StorageManager with all known strategies pre-registered.
     * Adding a new EOwnerType strategy only requires registering it here (OCP).
     */
    private function buildDefaultManager(): StorageManager
    {
        $repository = new StorageRepository();

        $strategies = [
            new PackageOwnerStorageStrategy($this->fileSystem, $repository, $this->basePath),
            // Register additional strategies here as new EOwnerTypes are introduced:
            // new UserOwnerStorageStrategy($this->fileSystem, $repository, $this->basePath),
            // new SystemOwnerStorageStrategy($this->fileSystem, $repository, $this->basePath),
        ];

        return new StorageManager($repository, $this->fileSystem, $strategies);
    }

    // ----------------------------------------------------------------
    // Private — upload helpers
    // ----------------------------------------------------------------

    /**
     * @throws \InvalidArgumentException
     */
    private function assertValidFileArray(array $file): void
    {
        foreach (['name', 'tmp_name', 'error', 'size'] as $key) {
            if (!array_key_exists($key, $file)) {
                throw new \InvalidArgumentException("Missing key '{$key}' in file array.");
            }
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException(
                "Upload failed for '{$file['name']}' with PHP error code {$file['error']}."
            );
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException(
                "Invalid or missing temp file for '{$file['name']}'."
            );
        }
    }

    /**
     * Move the uploaded temp file to staging and return its new path.
     *
     * @throws \RuntimeException
     */
    private function stageUploadedFile(array $file): string
    {
        $stagingDir = $this->basePath . self::STAGING_DIR;

        $this->fileSystem->makeDirectory($stagingDir);

        $safeName    = hash('SHA256', uniqid($file['name'], true))
            . '.'
            . pathinfo($file['name'], PATHINFO_EXTENSION);
        $destination = $stagingDir . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException(
                "Could not move uploaded file '{$file['name']}' to staging directory."
            );
        }

        return $destination;
    }

    private function buildEntry(
        string     $originalName,
        string     $stagedPath,
        EOwnerType $ownerType,
        ?int       $ownerId,
        ?string    $middleware
    ): StorageEntry {
        $entry = new StorageEntry();

        $entry->setName($originalName)
            ->setPath($stagedPath)
            ->setSize($this->fileSystem->fileSize($stagedPath))
            ->setLastModified($this->fileSystem->lastModified($stagedPath))
            ->setOwnerType($ownerType)
            ->setOwnerId($ownerId)
            ->setMiddleware($middleware);

        return $entry;
    }

    // ----------------------------------------------------------------
    // Private — download helpers
    // ----------------------------------------------------------------

    private function sendDownloadHeaders(StorageEntry $entry, string $disposition): void
    {
        if (!in_array($disposition, ['attachment', 'inline'], true)) {
            $disposition = 'attachment';
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($entry->getName()) . '"');
        header('Content-Length: ' . $entry->getSize());
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
}