<?php

namespace vfs\resource;

use cache\CacheControl;
use cache\enums\ECacheType;

class ResourceCache
{
    private const CACHE_NAME = 'vfs';

    private CacheControl $cache;

    public function __construct()
    {
        $this->cache = new CacheControl(self::CACHE_NAME);
    }

    /**
     * Write the VFS map to disk, storing the build timestamp for validation.
     *
     * CacheControl writes an includable PHP file that returns the array —
     * no manual var_export or file_put_contents needed.
     */
    public function write(array $map): bool
    {
        $map['built_at'] = time();

        return $this->cache
            ->setCacheContent(ECacheType::ARRAY, $map)
            ->saveCache();
    }

    /**
     * Load and validate the cache.
     *
     * Returns the map array on success, null if:
     *   - the cache file does not exist or is unreadable
     *   - any directory in the tree has been modified since the cache was built
     */
    public function load(): ?array
    {
        $map = $this->cache->loadCache();

        if (!is_array($map) || !isset($map['path'], $map['built_at']))
            return null;

        if ($this->isTreeDirty($map['path'], (int) $map['built_at']))
            return null;

        return $map;
    }

    /**
     * Delete the cache, forcing a rebuild on next load.
     */
    public function invalidate(): void
    {
        $this->cache->clearCache();
    }

    // -------------------------------------------------------------------------
    // Private — tree validation
    // -------------------------------------------------------------------------

    /**
     * Recursively checks whether any directory under $path has been modified
     * (file added, removed, or renamed) since $since.
     *
     * Only scans directory mtimes — no file reads, no hashing.
     * This intentionally bypasses CacheControl's watch snapshot system:
     * the VFS map represents a directory tree, and directory mtime is the
     * correct and cheapest signal for structural changes.
     */
    private function isTreeDirty(string $path, int $since): bool
    {
        if (!is_dir($path))
            return false;

        if (filemtime($path) > $since)
            return true;

        foreach (array_slice(scandir($path), 2) as $item) {
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full) && $this->isTreeDirty($full, $since))
                return true;
        }

        return false;
    }
}