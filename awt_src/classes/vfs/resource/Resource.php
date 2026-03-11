<?php

namespace vfs\resource;

use vfs\resource\event\ContextRequestEvent;

class Resource
{
    public string $context;
    private array $map;
    private string $path;

    public function __construct(string $context = "", string $path = PACKAGES)
    {
        global $eventDispatcher;

        if(empty($context)) {
            $contextEvent = new ContextRequestEvent();
            $eventDispatcher->dispatch($contextEvent);
            $this->context = $contextEvent->context;
        } else {
            $this->context = $context;
        }

        $this->path = $path;
        $this->map = [];
    }

    public function buildResourceMap(): self
    {
        $start = ResourceBuilder::directoryIterator($this->path);

        foreach($start as $resource) {
            if(is_dir($this->path . DIRECTORY_SEPARATOR . $resource)) {
                $builder = new ResourceBuilder($this->path . DIRECTORY_SEPARATOR . $resource);
                $map[$resource] = $builder->build();
                $this->map = array_merge($this->map, $map);
                ResourceCache::cache($resource, [$this->path . DIRECTORY_SEPARATOR . $resource], $map);
            }
        }

        return $this;
    }

    public function getResourceMap(): array
    {
        return $this->map;
    }

    /**
     * Get a resource by its alias.
     *
     * Example of alias:
     *
     * <filename.ext>
     *
     * <folder>/<filename.ext>
     *     or
     * <PackageName>:<filename.ext>
     *     or
     * <PackageName>:<folder>/<filename.ext>
     * @param string $alias
     * @param bool $must
     * @return string|null
     */
    public function get(string $alias, bool $must = false): ?string
    {
        $alias = $this->parseAlias($alias);

        $hasPackage = false;
        $hasPath = false;
        $hasFile = false;

        if(!empty($alias['package']))
            $hasPackage = true;

        if(!empty($alias['path']))
            $hasPath = true;

        if(!empty($alias['file']))
            $hasFile = true;


        if($hasPackage) {
            $res = ResourceCache::get($alias['package']);
            !is_array($res) ? $this->buildResourceMap() : $this->map = $res;
        } else {
            $res = ResourceCache::get($this->context);
            !is_array($res) ? $this->buildResourceMap() : $this->map = $res;
        }

        if(empty($this->map))
            return null;

        if ($hasPackage && $hasPath && $hasFile) {
            $node = $this->map[$alias['package']] ?? null;

            foreach (explode('/', $alias['path']) as $segment) {
                if (!is_array($node) || !isset($node[$segment])) return null;
                $node = $node[$segment];
            }

            return $node[$alias['file']] ?? null;
        }

        if ($hasPackage && $hasFile && !$hasPath) {
            $map = $this->map[$alias['package']] ?? [];

            // Direct file at package root (e.g. PackageName:main.php)
            if (isset($map[$alias['file']]) && is_string($map[$alias['file']])) {
                return $map[$alias['file']];
            }

            // File inside a subdirectory (e.g. PackageName:PackageNameController.php)
            foreach ($map as $dir => $entries) {
                if (is_array($entries) && isset($entries[$alias['file']])) {
                    return $entries[$alias['file']];
                }
            }
        }

        if ($hasPath && $hasFile && !$hasPackage) {
            $node = $this->map[$this->context] ?? null;

            foreach (explode('/', $alias['path']) as $segment) {
                if (!is_array($node) || !isset($node[$segment])) return null;
                $node = $node[$segment];
            }

            return $node[$alias['file']] ?? null;
        }

        if($hasFile && !$hasPackage && !$hasPath) {
            $map = $this->map[$this->context] ?? null;
            foreach($map as $key => $files) {
                if($key === $alias['file'])
                    return $files;

                if(is_array($files)) {
                     $res = $this->findInArray($files, $alias['file']);
                    if($res !== null)
                        return $res;
                }
            }
        }

        return null;
    }


    private function findInArray(array $array, $value): ?string
    {
        foreach ($array as $key => $item) {
            if ($key === $value) {
                return $item;
            }

            if(is_array($item)) {
                $res = $this->findInArray($item, $value);
                if($res !== null)
                    return $res;
            }
        }

        return null;
    }

    private function parseAlias(string $alias): ?array
    {
        // Normalize: strip leading slash after package colon (e.g. "Test:/main.php" → "Test:main.php")
        $alias = preg_replace('/^([^:]+):\//', '$1:', $alias);

        $pattern = '/^(?:(?<package>[^:]+):)?(?:(?<path>.+)\/)?(?<file>[^\/]+)$/';

        if (!preg_match($pattern, $alias, $matches)) {
            return null;
        }

        return [
            'package' => !empty($matches['package']) ? $matches['package'] : null,
            'path'    => !empty($matches['path'])    ? $matches['path']    : null,
            'file'    => $matches['file'],
        ];
    }
}