<?php

namespace vfs\resource;

use vfs\cache\Cache;
use vfs\cache\enums\ECacheValidation;

class ResourceCache
{
    public static function cache(string $context, array $watch, array $content): void
    {
        $cache = new Cache();
        $cache->pool("resource")->createConfig(ECacheValidation::MODIFIED, $watch)->setCache($context, $content);
    }

    public static function get(string $context): array|bool
    {
        return Cache::get("resource", $context);
    }
}