<?php
namespace object;

class ObjectHandler
{
    /** Select a class declared by this file, rather than a dependency it autoloaded. */
    public static function classFromFile(string $filename): ?string
    {
        $path = realpath($filename);
        if ($path === false || !is_file($path)) throw new \RuntimeException("File not found: {$filename}");
        include_once $path;
        foreach (get_declared_classes() as $class) {
            $reflection = new \ReflectionClass($class);
            if ($reflection->getFileName() === $path && !$reflection->isAbstract()) return $class;
        }
        return null;
    }
    public static function createObjectFromFile(string $filename): ?object
    {
        $class = self::classFromFile($filename);
        return $class === null ? null : self::createObject($class);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public static function createObject(string $className): object
    {
        return new $className();
    }
}
