<?php

namespace vfs\resource;

/** HTTP access policy; internal Resource lookup intentionally includes framework source files. */
final class PublicResource
{
    private const EXTENSIONS = ['json', 'txt', 'xml', 'html', 'htm', 'css', 'js', 'mjs', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico',
        'bmp', 'avif', 'tiff', 'mp4', 'webm', 'ogv', 'mov', 'mp3', 'wav', 'ogg', 'oga', 'aac', 'flac',
        'm4a', 'weba', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'pdf'];
    private const DIRECTORIES = ['assets', 'public', 'views/assets', 'data', 'css', 'js', 'images', 'fonts', 'videos', 'audio'];

    public static function allows(string $package, string $path, string $root = PACKAGES): bool
    {
        $directory = realpath(rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $package);
        $file = realpath($path);
        if ($directory === false || $file === false || !is_file($file) || !is_readable($file)
            || !str_starts_with($file, $directory . DIRECTORY_SEPARATOR)) return false;
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($directory) + 1));
        foreach (explode('/', $relative) as $segment) if (str_starts_with($segment, '.')) return false;
        if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) return false;
        foreach (self::DIRECTORIES as $allowed) {
            if (str_starts_with($relative, $allowed . '/')) return true;
        }
        return false;
    }
}
