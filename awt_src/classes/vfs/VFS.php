<?php

namespace vfs;

use vfs\enums\EVFSType;
use vfs\event\ContextRequestEvent;

class VFS
{
    public array $root;
    public string $context;

    public function __construct()
    {
        global $eventDispatcher;
        $this->loadCache();
        $contextEvent = new ContextRequestEvent();
        $eventDispatcher->dispatch($contextEvent);
        $this->context = $contextEvent->bundle()["context"]->contextName;
    }

    public function loadCache(): void
    {
        if (file_exists(CACHE . "vfs.php")) {
            $this->root = require CACHE . "vfs.php";
            if ($this->root["hash"] !== hash("sha1", $this->root["path"]))
                $this->initBuild();
            return;
        }

        $this->initBuild();
    }

    public function initBuild(): void
    {
        $builder = new VFSBuilder();
        $builder->cache();
        $this->root = $builder->root->__toArray();
    }

    /**
     * Gets the path of a file or directory in the VFS based on the access.
     *
     * To access your own files, use the following format:
     * <file.ext>
     *     or
     * <directory>:/<file.ext> (if you have multiple files with the same name)
     *
     * To access files from other packages use the following format:
     * <package>:<file.ext>
     *     or
     * <package>:<directory>:/<file.ext> (if it has multiple files with the same name)
     *
     * @param string $access
     * @return string|null
     */
    public function get(string $access): ?string
    {
        // Split on ':' not followed by '/' — this is the package separator
        // e.g. 'PackageName:file.ext' or 'PackageName:directory:/file.ext'
        $packageName = null;
        $rest        = $access;

        if (preg_match('/^([^:]+):(?!\/)(.+)$/', $access, $match)) {
            $packageName = trim($match[1]);
            $rest        = trim($match[2]);
        }

        // Resolve the package node to search within
        $packageNode = $this->findPackage($packageName ?? $this->context);
        if ($packageNode === null) return null;

        // Split on ':/' — this is the directory separator
        // e.g. 'directory:/file.ext' → ['directory', 'file.ext']
        //      'file.ext'            → ['file.ext']
        $parts = array_map('trim', explode(':/', $rest));

        if (count($parts) === 1) {
            // No directory qualifier — search anywhere in the package
            return $this->searchByAlias($packageNode, $parts[0]);
        }

        // Walk down through each directory segment, then find the final alias
        $dirSegments = array_slice($parts, 0, -1);
        $fileAlias   = end($parts);

        $node = $packageNode;
        foreach ($dirSegments as $dir) {
            $node = $this->findDirectChild($node, $dir);
            if ($node === null) return null;
        }

        return $this->searchByAlias($node, $fileAlias);
    }


    /**
     * Find a direct first-level package node under root by alias.
     */
    private function findPackage(string $alias): ?array
    {
        foreach ($this->root['children'] as $child) {
            if ($child['alias'] === $alias) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Find a direct (non-recursive) child of a node by alias.
     */
    private function findDirectChild(array $node, string $alias): ?array
    {
        foreach ($node['children'] as $child) {
            if ($child['alias'] === $alias) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Depth-first search for an alias anywhere inside a node's subtree.
     * Returns the real filesystem path on first match.
     */
    private function searchByAlias(array $node, string $alias): ?string
    {
        foreach ($node['children'] as $child) {
            if ($child['alias'] === $alias) {
                return $child['path'];
            }

            if (!empty($child['children'])) {
                $result = $this->searchByAlias($child, $alias);
                if ($result !== null) return $result;
            }
        }
        return null;
    }
}