<?php

namespace vfs\resource;

class ResourceBuilder
{
    private string $path;
    private array $resources = [];


    public function __construct(string $path = PACKAGES)
    {
        $this->path = $path;
    }

    public function build(): array
    {
        $this->resources = self::directoryIterator($this->path, true);
        return $this->resources;
    }

    public static function directoryIterator(string $path, bool $recursive = false): array
    {
        $scanned = [];

        if(!is_dir($path)) return $scanned;

        $files = array_diff(scandir($path), ['.', '..']);

        if(!$recursive) return $files;

        foreach($files as $file) {
            if(is_dir($path . DIRECTORY_SEPARATOR . $file)) {
                $scanned[$file] = self::directoryIterator($path . DIRECTORY_SEPARATOR . $file, true);
            } else {
                $scanned[$file] =  $path . DIRECTORY_SEPARATOR . $file;
            }
        }

        return $scanned;
    }
}