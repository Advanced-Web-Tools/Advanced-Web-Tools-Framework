<?php

namespace vfs\resource;

use vfs\resource\event\ContextRequestEvent;
use vfs\resource\exceptions\EResourceException;
use vfs\resource\exceptions\ResourceException;

class Resource
{
    public array   $root;
    public ?string $context;
    public string $access;

    public function __construct()
    {
        global $eventDispatcher;
        $this->loadCache();
        $contextEvent = new ContextRequestEvent();
        $eventDispatcher->dispatch($contextEvent);
        $this->context = $contextEvent->bundle()["context"]->contextName ?? null;
    }

    public function setContext(string $context): void
    {
        $this->context = $context;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function loadCache(): void
    {
        $cache = new ResourceCache();
        $map   = $cache->load();

        if ($map !== null) {
            $this->root = $map;
            return;
        }

        $this->initBuild();
    }

    public function initBuild(): void
    {
        $builder    = new ResourceBuilder();
        $builder->cache();
        $this->root = $builder->root->__toArray();
    }

    /**
     * Gets the path of a file or directory in the VFS based on the access string.
     *
     * Own files:
     *   <file.ext>
     *   <directory>:/<file.ext>        (disambiguate by directory)
     *
     * Other package files:
     *   <package>:<file.ext>
     *   <package>:<directory>:/<file.ext>
     *
     * @param string $access
     * @param bool   $must   Throw if the file cannot be resolved or does not exist on disk.
     * @throws ResourceException
     */
    public function get(string $access, bool $must = false): ?string
    {
        $this->access = $access;

        if ($this->context === null) {
            throw new ResourceException(EResourceException::MissingContext, $this);
        }

        [$packageName, $rest] = $this->parseAccess($access);

        $packageNode = $this->findNodeByAlias($this->root, $packageName ?? $this->context);
        if ($packageNode === null) {
            return null;
        }

        $path = $this->resolvePath($packageNode, $rest);

        $this->assertFound($path, $must);

        return $path;
    }


    /**
     * Splits an access string into [packageName|null, rest].
     *
     * 'PackageName:file.ext'            → ['PackageName', 'file.ext']
     * 'PackageName:directory:/file.ext' → ['PackageName', 'directory:/file.ext']
     * 'file.ext'                        → [null, 'file.ext']
     *
     * @return array{0: string|null, 1: string}
     */
    private function parseAccess(string $access): array
    {
        // ':' not followed by '/' is the package separator
        if (preg_match('/^([^:]+):(?!\/)(.+)$/', $access, $match)) {
            return [trim($match[1]), trim($match[2])];
        }

        return [null, $access];
    }

    /**
     * Splits a rest string into directory segments and a final file alias.
     *
     * 'directory:/file.ext'        → [['directory'], 'file.ext']
     * 'dir:/subdir:/file.ext'      → [['dir', 'subdir'], 'file.ext']
     * 'file.ext'                   → [[], 'file.ext']
     *
     * @return array{0: string[], 1: string}
     */
    private function parsePath(string $rest): array
    {
        $parts = array_map('trim', explode(':/', $rest));

        $fileAlias   = array_pop($parts);
        $dirSegments = $parts;

        return [$dirSegments, $fileAlias];
    }


    /**
     * Walks a package node using parsed path segments and returns the real path.
     */
    private function resolvePath(array $packageNode, string $rest): ?string
    {
        [$dirSegments, $fileAlias] = $this->parsePath($rest);

        $node = $packageNode;
        foreach ($dirSegments as $dir) {
            $node = $this->findNodeByAlias($node, $dir);
            if ($node === null) {
                return null;
            }
        }

        return $this->searchByAlias($node, $fileAlias);
    }

    /**
     * Finds a direct (non-recursive) child of a node by alias.
     */
    private function findNodeByAlias(array $node, string $alias): ?array
    {
        foreach ($node['children'] as $child) {
            if ($child['alias'] === $alias) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Depth-first search for an alias anywhere in a node's subtree.
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
                if ($result !== null) {
                    return $result;
                }
            }
        }
        return null;
    }


    /**
     * Enforces the $must contract:
     *   - null path   → file not in the node tree at all  (FileNotFound)
     *   - path exists → fine
     *   - path absent → node tree knows it, disk doesn't  (FileExistsButNotFound)
     *
     * @throws ResourceException
     */
    private function assertFound(?string $path, bool $must): void
    {
        if (!$must) {
            return;
        }

        if ($path === null) {
            throw new ResourceException(EResourceException::FileNotFound, $this);
        }

        if (!file_exists($path)) {
            throw new ResourceException(EResourceException::FileExistsButNotFound, $this);
        }
    }
}