<?php

namespace installer\framework;

final class FrameworkVersion
{
    public const FILE = '.awt-version';

    public static function current(?string $root = null): string
    {
        $file = ($root ?? ROOT) . '/' . self::FILE;
        if (!is_link($file) && is_file($file)) {
            $version = trim((string) file_get_contents($file));
            if (preg_match('/^\d+\.\d+\.\d+$/D', $version)) return $version;
        }
        return AWT_VERSION;
    }
}
