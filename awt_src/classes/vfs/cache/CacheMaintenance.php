<?php

namespace vfs\cache;

use vfs\resource\Resource;

/** Maintenance for generated framework caches, including VFS resource maps. */
class CacheMaintenance
{
    public function clear(): int
    {
        $root = DATA . 'storage/framework/cache';
        if (is_link($root)) throw new \RuntimeException('Cache root must not be a symbolic link.');
        // Discard pool configuration retained by the interactive CLI as well.
        Cache::resetPools();
        return is_dir($root) ? $this->clearDirectory($root) : 0;
    }

    public function rebuild(): int
    {
        if (!is_dir(PACKAGES) || !is_readable(PACKAGES)) {
            throw new \RuntimeException('Package directory is missing or unreadable.');
        }
        $this->clear();
        $resource = (new Resource('System'))->buildResourceMap();
        return count($resource->getResourceMap());
    }

    private function clearDirectory(string $directory): int
    {
        $names = scandir($directory);
        if ($names === false) throw new \RuntimeException("Cannot scan cache directory: {$directory}");
        $count = 0;
        foreach ($names as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (!is_link($path) && is_dir($path)) {
                $count += $this->clearDirectory($path);
                if (!rmdir($path)) throw new \RuntimeException("Cannot remove cache directory: {$path}");
            } else {
                if (function_exists('opcache_invalidate') && !is_link($path)) opcache_invalidate($path, true);
                if (!unlink($path)) throw new \RuntimeException("Cannot remove cache file: {$path}");
                ++$count;
            }
        }
        return $count;
    }
}
