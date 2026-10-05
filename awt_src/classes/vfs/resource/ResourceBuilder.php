<?php

namespace vfs\resource;

class ResourceBuilder
{
    public function __construct(private string $path = PACKAGES) {}
    public function build(): array { return self::directoryIterator($this->path, true); }

    public static function directoryIterator(string $path, bool $recursive = false): array
    {
        return self::scan($path, $recursive, []);
    }

    private static function scan(string $path, bool $recursive, array $ancestors): array
    {
        if (!is_dir($path)) return [];
        $real = realpath($path);
        if ($real === false || isset($ancestors[$real])) return [];
        $ancestors[$real] = true;
        $names = @scandir($path);
        if ($names === false) throw new exceptions\ResourceException("Cannot scan resource directory: {$path}");
        $names = array_values(array_diff($names, ['.', '..']));
        if (!$recursive) return $names;
        $result = [];
        foreach ($names as $name) {
            $file = $path . DIRECTORY_SEPARATOR . $name;
            if (is_dir($file)) {
                // Descendant directory links are excluded from maps and cache watches.
                if (!is_link($file)) $result[$name] = self::scan($file, true, $ancestors);
            } elseif (is_file($file)) $result[$name] = $file;
        }
        return $result;
    }
}
