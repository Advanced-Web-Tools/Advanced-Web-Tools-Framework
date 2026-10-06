<?php

namespace uninstaller\cleanup;

use uninstaller\interfaces\IPackageCleanup;
use vfs\resource\ResourceCache;

class ResourceCleanup implements IPackageCleanup
{
    public function __construct(private readonly string $packageRoot) {}

    public function clean(int $id, string $name): array
    {
        return ResourceCache::delete($name, $this->packageRoot)
            ? [] : ["Failed to delete resource entries for {$name}."];
    }
}
