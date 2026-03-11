<?php

namespace vfs\cache;

class Cache
{
    private static array $pools = [];

    public static function pool(string $name): CachePool
    {
        if (!isset(self::$pools[$name])) {
            self::$pools[$name] = new CachePool($name);
        }

        return self::$pools[$name];
    }

    public static function get(string $pool, string $key): bool|array
    {
        return self::pool($pool)->getCache($key);
    }

    public static function set(string $pool, string $key, array $data): CacheEntry
    {
        return self::pool($pool)->setCache($key, $data);
    }

    public static function delete(string $pool, string $key): bool
    {
        return self::pool($pool)->deleteCache($key);
    }
}