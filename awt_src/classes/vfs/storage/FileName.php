<?php

namespace vfs\storage;

final class FileName
{
    public static function unique(string $directory, string $source): string
    {
        $extension = pathinfo($source, PATHINFO_EXTENSION);
        return rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . bin2hex(random_bytes(24))
            . ($extension === '' ? '' : '.' . $extension);
    }
}
