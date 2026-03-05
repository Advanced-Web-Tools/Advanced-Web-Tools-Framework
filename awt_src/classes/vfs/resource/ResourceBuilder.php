<?php

namespace vfs\resource;

use object\ObjectCollection;
use vfs\resource\enums\EResourceType;

class ResourceBuilder
{
    public ResourceEntry $root;
    private ResourceCache $cache;

    public function __construct()
    {
        $this->cache = new ResourceCache();
        $this->root  = new ResourceEntry("root", PACKAGES, EResourceType::Directory);
        $this->scanDirectory(PACKAGES, $this->root);
    }

    private function scanDirectory(string $path, ResourceEntry $parent): void
    {
        $scanned = array_slice(scandir($path), 2);

        foreach ($scanned as $item) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            $type     = is_dir($fullPath) ? EResourceType::Directory : EResourceType::File;
            $entry    = new ResourceEntry($item, $fullPath, $type);
            $parent->addChild($entry);

            if ($type === EResourceType::Directory) {
                $this->scanDirectory($fullPath, $entry);
            }
        }
    }

    public function build(): ObjectCollection
    {
        return $this->root->children;
    }

    public function cache(): void
    {
        $this->cache->write($this->root->__toArray());
    }
}