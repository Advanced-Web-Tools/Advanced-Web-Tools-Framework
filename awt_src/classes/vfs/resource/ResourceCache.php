<?php

namespace vfs\resource;

use vfs\cache\Cache;
use vfs\cache\enums\ECacheValidation;

class ResourceCache
{
    public static function cache(string $context, array $watch, array $content, string $root = PACKAGES): void
    {
        $cache = new Cache();
        $cache->pool("resource")->createConfig(ECacheValidation::MODIFIED, $watch)->setCache(self::key($context, $root), $content);
    }

    public static function get(string $context, string $root = PACKAGES): array|bool
    {
        return Cache::get("resource", self::key($context, $root));
    }
    private static function key(string $context, string $root): string
    {
        return $context . '@' . hash('sha256', realpath($root) ?: rtrim($root, DIRECTORY_SEPARATOR));
    }
}
