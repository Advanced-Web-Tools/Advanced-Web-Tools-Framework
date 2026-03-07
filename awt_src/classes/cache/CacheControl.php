<?php

namespace cache;

use cache\enums\ECacheType;
use cache\enums\ECacheValidationMethod;
use vfs\storage\providers\default\TransientStorage;

class CacheControl
{
    private const META_FILE       = 'meta';
    private const META_EXT        = 'json';
    private const CONTENT_FILE    = 'content';
    private const ARRAY_CACHE_EXT = 'php';
    private const FILE_CACHE_EXT  = 'cache';

    private TransientStorage $transientStorage;
    private string $cacheName;

    /**
     * Stores cache metadata in format:
     *
     * [
     *   "file"              => <cache filename>,
     *   "lastModified"      => <unix timestamp>,
     *   "hash"              => <xxh3 hash of written body>,
     *   "type"              => <ECacheType value>,
     *   "validation"        => <ECacheValidationMethod value>,
     *   "watch"             => [
     *       ["path" => <abs path>, "kind" => "file"|"dir"],
     *       ...
     *   ],
     *   "watch_snapshots"   => [
     *       <abs file path> => ["mtime" => <int>, "hash" => <string>],
     *       ...
     *   ],
     * ]
     *
     * @var array $cacheInfo
     */
    private array $cacheInfo = [];

    /** @var list<array{path: string, kind: string}> */
    private array $watchTargets = [];

    private ECacheType $cacheType;
    private string|array $cacheContent;

    public function __construct(string $cacheName)
    {
        $this->transientStorage = new TransientStorage();
        $this->cacheName        = $cacheName;

        $this->transientStorage->selectPool('cache');

        if (trim($cacheName) !== '')
            $this->transientStorage->selectSubPool(trim($cacheName));
    }

    // -------------------------------------------------------------------------
    // Public API — configuration
    // -------------------------------------------------------------------------

    public function setCacheContent(ECacheType $type, string|array $content): self
    {
        $this->cacheType    = $type;
        $this->cacheContent = $content;
        $this->updateCacheInfo('type', $type->value);
        return $this;
    }

    public function setCacheValidation(ECacheValidationMethod $validation): self
    {
        $this->updateCacheInfo('validation', $validation->value);
        return $this;
    }

    /**
     * Registers a single file whose state (mtime or hash) will be snapshotted
     * on save and re-checked on every isCacheValid() call.
     *
     * Pair with ECacheValidationMethod::TIME_MODIFIED or ::HASH.
     */
    public function watchFile(string $path): self
    {
        if (!file_exists($path))
            throw new \InvalidArgumentException("Watched file does not exist: {$path}");

        if (!is_file($path))
            throw new \InvalidArgumentException("Path is not a file (use watchDirectory): {$path}");

        $this->watchTargets[] = ['path' => $path, 'kind' => 'file'];
        return $this;
    }

    /**
     * Registers a directory. Every file within it (recursively) is snapshotted
     * on save and re-checked on every isCacheValid() call.
     *
     * Pair with ECacheValidationMethod::TIME_MODIFIED or ::HASH.
     */
    public function watchDirectory(string $path): self
    {
        if (!is_dir($path))
            throw new \InvalidArgumentException("Watched directory does not exist: {$path}");

        $this->watchTargets[] = ['path' => $path, 'kind' => 'dir'];
        return $this;
    }

    // -------------------------------------------------------------------------
    // Public API — persistence
    // -------------------------------------------------------------------------

    /**
     * Writes the cache content file, snapshots all watched paths, and persists metadata.
     */
    public function saveCache(): bool
    {
        $written = $this->write();

        if ($written === null)
            return false;

        [$filename, $extension, $body] = $written;

        $contentEntry = $this->transientStorage->makeTransientStorageEntry($filename, $extension, $body);

        if ($contentEntry === null)
            return false;

        $this->updateCacheInfo('file',         $contentEntry->getFullName());
        $this->updateCacheInfo('lastModified', (string) time());
        $this->updateCacheInfo('hash',         hash('xxh3', $body));

        if (!empty($this->watchTargets)) {
            $this->cacheInfo['watch']           = $this->watchTargets;
            $this->cacheInfo['watch_snapshots'] = $this->buildWatchSnapshots($this->watchTargets);
        }

        return $this->saveCacheInfo();
    }

    /**
     * Loads and returns the cache content from disk.
     * Returns array for ARRAY caches, raw string or unserialized array for FILE caches.
     */
    public function loadCache(): string|array|null
    {
        $info = $this->loadCacheInfo();

        if ($info === null)
            return null;

        $type  = ECacheType::from($info['type'] ?? 'file');
        $entry = $this->transientStorage->getTransientStorageEntry($info['file'] ?? '');

        if ($entry === null)
            return null;

        $content = $entry->getContent() ?? '';

        return match($type) {
            ECacheType::ARRAY => include $entry->getPath(),
            ECacheType::FILE  => str_starts_with($content, 'a:') || str_starts_with($content, 'O:')
                ? unserialize($content)
                : $content,
        };
    }

    /**
     * Checks whether the cache is still valid.
     *
     * When watched paths are present in the stored metadata, ALL of them must
     * pass validation — a single changed file invalidates the whole cache.
     * When no watched paths are stored, the cache file itself is validated.
     *
     * TIME_MODIFIED / EXPIRY_* compare mtime; HASH compares xxh3 hashes.
     */
    public function isCacheValid(): bool
    {
        $info = $this->loadCacheInfo();

        if ($info === null || empty($info['file']) || empty($info['validation']))
            return false;

        $method = ECacheValidationMethod::tryFrom($info['validation']);

        if ($method === null)
            return false;

        // --- Validate against watched paths if any were registered on save ---
        if (!empty($info['watch']) && !empty($info['watch_snapshots']))
            return $this->validateWatchedPaths($info['watch'], $info['watch_snapshots'], $method);

        // --- Fall back: validate the cache file itself -----------------------
        $entry = $this->transientStorage->getTransientStorageEntry($info['file']);

        if ($entry === null)
            return false;

        return $method->validate($entry->getPath(), $info['hash'] ?? null);
    }

    /**
     * Deletes all files in the current cache subpool, forcing a rebuild on next load.
     */
    public function clearCache(): bool
    {
        return $this->transientStorage->clearTransientStorage();
    }

    // -------------------------------------------------------------------------
    // Private — watch path logic
    // -------------------------------------------------------------------------

    /**
     * Resolves all watch targets to individual files, then checks each one
     * against the stored snapshot. Returns false as soon as one fails.
     *
     * A file appearing in a watched directory that has no snapshot entry is
     * treated as a change (new file = stale cache).
     *
     * @param list<array{path: string, kind: string}>           $targets
     * @param array<string, array{mtime: int, hash: string}>    $snapshots
     */
    private function validateWatchedPaths(array $targets, array $snapshots, ECacheValidationMethod $method): bool
    {
        $resolvedFiles = $this->resolveWatchedFiles($targets);

        // A file that existed at save time has since been deleted
        foreach (array_keys($snapshots) as $snapshotted) {
            if (!file_exists($snapshotted))
                return false;
        }

        foreach ($resolvedFiles as $file) {
            // A new file appeared in a watched directory
            if (!isset($snapshots[$file]))
                return false;

            if (!$method->validateAgainstSnapshot($file, $snapshots[$file]))
                return false;
        }

        return true;
    }

    /**
     * Builds a snapshot map from the current on-disk state of all watched files.
     *
     * @param  list<array{path: string, kind: string}>
     * @return array<string, array{mtime: int, hash: string}>
     */
    private function buildWatchSnapshots(array $targets): array
    {
        $snapshots = [];

        foreach ($this->resolveWatchedFiles($targets) as $file) {
            $snapshots[$file] = [
                'mtime' => (int) filemtime($file),
                'hash'  => hash_file('xxh3', $file),
            ];
        }

        return $snapshots;
    }

    /**
     * Expands a list of watch targets into a flat list of absolute file paths.
     *
     * @param  list<array{path: string, kind: string}> $targets
     * @return list<string>
     */
    private function resolveWatchedFiles(array $targets): array
    {
        $files = [];

        foreach ($targets as ['path' => $path, 'kind' => $kind]) {
            if ($kind === 'file') {
                if (file_exists($path))
                    $files[] = $path;
                continue;
            }

            if (is_dir($path)) {
                foreach ($this->scanFilesRecursively($path) as $file)
                    $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Returns an absolute path for every file under $directory, recursively.
     *
     * @return list<string>
     */
    private function scanFilesRecursively(string $directory): array
    {
        $results  = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile())
                $results[] = $file->getPathname();
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private — write
    // -------------------------------------------------------------------------

    /**
     * @return array{0: string, 1: string, 2: string}|null  [filename, extension, body]
     */
    private function write(): ?array
    {
        if (!isset($this->cacheType) || !isset($this->cacheContent))
            return null;

        return match($this->cacheType) {
            ECacheType::ARRAY => $this->writeArrayCache(),
            ECacheType::FILE  => $this->writeFileCache(),
        };
    }

    /**
     * Produces an includable PHP file: <?php return [...];
     * Content MUST be an array.
     */
    private function writeArrayCache(): ?array
    {
        if (!is_array($this->cacheContent))
            return null;

        $exported = var_export($this->cacheContent, return: true);
        $body     = "<?php\n\nreturn {$exported};\n";

        return [self::CONTENT_FILE, self::ARRAY_CACHE_EXT, $body];
    }

    /**
     * Serialises arrays; writes strings as-is.
     */
    private function writeFileCache(): array
    {
        $body = is_array($this->cacheContent)
            ? serialize($this->cacheContent)
            : $this->cacheContent;

        return [self::CONTENT_FILE, self::FILE_CACHE_EXT, $body];
    }

    // -------------------------------------------------------------------------
    // Private — cache info (metadata)
    // -------------------------------------------------------------------------

    private function updateCacheInfo(string $key, string $value): void
    {
        $this->cacheInfo[$key] = $value;
    }

    private function saveCacheInfo(): bool
    {
        $json = json_encode($this->cacheInfo, JSON_PRETTY_PRINT);
        return $this->transientStorage->makeTransientStorageEntry(self::META_FILE, self::META_EXT, $json) !== null;
    }

    private function loadCacheInfo(): ?array
    {
        $entry = $this->transientStorage->getTransientStorageEntry(self::META_FILE . '.' . self::META_EXT);

        if ($entry === null)
            return null;

        $decoded = json_decode($entry->getContent() ?? '{}', associative: true);

        return is_array($decoded) ? $decoded : null;
    }
}