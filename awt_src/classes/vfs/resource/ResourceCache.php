<?php

namespace vfs\resource;

class ResourceCache
{
    private const CACHE_FILE = 'vfs.php';

    /**
     * Write the VFS map to disk, storing the build timestamp for validation.
     */
    public function write(array $map): void
    {
        $map['built_at'] = time();

        $output = "<?php\n\n return " . var_export($map, true) . ";\n";
        file_put_contents(CACHE . self::CACHE_FILE, $output);
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
        $file = CACHE . self::CACHE_FILE;

        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }

        $map = require $file;

        if (!is_array($map) || !isset($map['built_at'])) {
            return null;
        }

        if ($this->isTreeDirty($map['path'], $map['built_at'])) {
            return null;
        }

        return $map;
    }

    /**
     * Delete the cache file, forcing a rebuild on next load.
     */
    public function invalidate(): void
    {
        $file = CACHE . self::CACHE_FILE;
        if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Recursively checks whether any directory under $path has been modified
     * (file added, removed, or renamed) since $since.
     *
     * Only scans directory mtimes — no file reads, no hashing.
     */
    private function isTreeDirty(string $path, int $since): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        if (filemtime($path) > $since) {
            return true;
        }

        foreach (array_slice(scandir($path), 2) as $item) {
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full) && $this->isTreeDirty($full, $since)) {
                return true;
            }
        }

        return false;
    }
}