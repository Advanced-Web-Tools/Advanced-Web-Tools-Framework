<?php

namespace vfs\resource;

use vfs\resource\event\ContextRequestEvent;
use vfs\resource\exceptions\ResourceException;

class Resource
{
    public string $context;
    private array $map = [];
    private string $path;

    public function __construct(string $context = '', string $path = PACKAGES)
    {
        if ($context === '') {
            global $eventDispatcher;
            if (!isset($eventDispatcher)) throw new ResourceException('No resource context dispatcher is available.');
            $event = new ContextRequestEvent();
            $eventDispatcher->dispatch($event);
            $context = $event->context ?? '';
            if ($context === '') throw new ResourceException('No resource context was supplied.');
        }
        $this->context = $context;
        $this->path = rtrim($path, DIRECTORY_SEPARATOR);
    }

    public function buildResourceMap(): self
    {
        $this->map = [];
        foreach (ResourceBuilder::directoryIterator($this->path) as $package) {
            $directory = $this->path . DIRECTORY_SEPARATOR . $package;
            if (!is_dir($directory)) continue;
            $map = [$package => (new ResourceBuilder($directory))->build()];
            $this->map[$package] = $map[$package];
            ResourceCache::cache($package, [$directory], $map, $this->path);
        }
        return $this;
    }

    public function getResourceMap(): array { return $this->map; }

    /** Resolve filename, relative/path, or Package:relative/path. Required resources throw when missing. */
    public function get(string $alias, bool $must = false): ?string
    {
        $parts = $this->parseAlias($alias);
        $found = null;
        if ($parts !== null) {
            [$package, $relative] = $parts;
            $cached = ResourceCache::get($package, $this->path);
            if (is_array($cached)) $this->map = $cached;
            else $this->buildResourceMap();
            $map = $this->map[$package] ?? [];
            if (!str_contains($relative, '/')) $found = $this->findInArray($map, $relative);
            else {
                $node = $map;
                foreach (explode('/', $relative) as $segment) {
                    if (!is_array($node) || !array_key_exists($segment, $node)) { $node = null; break; }
                    $node = $node[$segment];
                }
                if (is_string($node)) $found = $node;
            }
            if ($found !== null && !is_file($found)) $found = null;
        }
        if ($found === null && $must) throw new ResourceException("Resource not found: {$alias}");
        return $found;
    }

    private function findInArray(array $array, string $name): ?string
    {
        if (isset($array[$name]) && is_string($array[$name])) return $array[$name];
        foreach ($array as $item) {
            if (is_array($item)) {
                $found = $this->findInArray($item, $name);
                if ($found !== null) return $found;
            }
        }
        return null;
    }

    private function parseAlias(string $alias): ?array
    {
        $package = $this->context;
        if (str_contains($alias, ':')) {
            [$package, $alias] = explode(':', $alias, 2);
            $alias = ltrim($alias, '/');
        }
        if ($package === '' || str_contains($package, '/') || str_contains($package, '\\')
            || $package === '.' || $package === '..' || str_contains($package, "\0")) return null;
        if ($alias === '' || str_contains($alias, '\\') || str_contains($alias, "\0")) return null;
        foreach (explode('/', $alias) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') return null;
        }
        return [$package, $alias];
    }
}
