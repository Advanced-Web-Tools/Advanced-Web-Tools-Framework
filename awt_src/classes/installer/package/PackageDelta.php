<?php

namespace installer\package;

use FilesystemIterator;
use package\manifest\reader\ManifestReader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

final class PackageDelta
{
    public const METADATA = 'awt-delta.json';

    public static function create(string $previous, string $next, string $output): void
    {
        $before = self::release($previous);
        $after = self::release($next);
        if ($before['name'] !== $after['name'] || !version_compare($after['version'], $before['version'], '>')) {
            throw new RuntimeException('A delta requires two releases of the same package in ascending version order.');
        }

        $old = self::files($previous);
        $new = self::files($next);
        $changes = [];
        foreach (array_keys($old + $new) as $path) {
            $baseHash = isset($old[$path]) ? self::hash($old[$path]) : null;
            $targetHash = isset($new[$path]) ? self::hash($new[$path]) : null;
            if ($baseHash !== $targetHash) {
                $changes[] = ['path' => (string) $path, 'before' => $baseHash, 'after' => $targetHash];
            }
        }

        $archive = new ZipArchive();
        if (!is_dir(dirname($output))) {
            throw new RuntimeException('The delta output directory does not exist.');
        }
        $temporary = tempnam(dirname($output), '.awt-delta-');
        if ($temporary === false) {
            throw new RuntimeException('Could not create a delta archive in the output directory.');
        }
        try {
            if ($archive->open($temporary, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not open the delta archive for writing.');
            }
            try {
                $metadata = json_encode(['format' => 1, 'base_version' => $before['version'], 'changes' => $changes], JSON_THROW_ON_ERROR);
                if (!$archive->addFromString(self::METADATA, $metadata) || !$archive->addFile($new['manifest.json'], 'manifest.json')) {
                    throw new RuntimeException('Could not write delta metadata.');
                }
                foreach ($changes as $change) {
                    $path = $change['path'];
                    if ($path !== 'manifest.json' && $change['after'] !== null && !$archive->addFile($new[$path], 'files/' . $path)) {
                        throw new RuntimeException("Could not add delta file: {$path}.");
                    }
                }
            } finally {
                if (!$archive->close()) {
                    throw new RuntimeException('Could not finish writing the delta archive.');
                }
            }
            if (!@link($temporary, $output)) {
                throw new RuntimeException('Could not publish the delta archive. Use a new output filename.');
            }
        } finally {
            unlink($temporary);
        }
    }

    public static function stage(string $delta, string $installed, string $installedVersion, array $target, string $stage): void
    {
        $base = self::release($installed);
        $json = file_get_contents($delta . '/' . self::METADATA);
        if ($json === false) throw new RuntimeException('Could not read delta metadata.');
        $metadata = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (($metadata['format'] ?? null) !== 1 || !is_array($metadata['changes'] ?? null) || !array_is_list($metadata['changes'])) {
            throw new RuntimeException('Unsupported or invalid package delta format.');
        }
        if (($metadata['base_version'] ?? null) !== $installedVersion || $installedVersion !== $base['version']
            || $base['name'] !== $target['name'] || !version_compare($target['version'], $installedVersion, '>')) {
            throw new RuntimeException("This delta does not apply to installed package {$base['name']} {$installedVersion}. Use the full release ZIP.");
        }

        $directories = [];
        $files = self::files($installed, $directories);
        $seen = [];
        foreach ($metadata['changes'] as $change) {
            $path = is_array($change) ? ($change['path'] ?? null) : null;
            if (!is_string($path) || !self::validPath($path) || isset($seen[$path])
                || !array_key_exists('before', $change) || !array_key_exists('after', $change)) {
                throw new RuntimeException('The delta contains an invalid or duplicate file change.');
            }
            $seen[$path] = true;
            $before = $change['before'];
            $after = $change['after'];
            if ($before === $after || !self::validHash($before) || !self::validHash($after)) {
                throw new RuntimeException("Invalid delta hashes for {$path}.");
            }
            $actual = isset($files[$path]) ? self::hash($files[$path]) : null;
            if ($before !== $actual) {
                throw new RuntimeException("Installed file differs from the delta base: {$path}. Use the full release ZIP.");
            }
            if ($after === null) {
                unset($files[$path]);
                continue;
            }
            $payload = $delta . ($path === 'manifest.json' ? '/' : '/files/') . $path;
            if (!is_file($payload) || is_link($payload) || self::hash($payload) !== $after) {
                throw new RuntimeException("Delta payload is missing or damaged: {$path}.");
            }
            $files[$path] = $payload;
        }

        if (!isset($seen['manifest.json']) || ($files['manifest.json'] ?? null) !== $delta . '/manifest.json' || !isset($files['main.php'])) {
            throw new RuntimeException('The reconstructed release must contain the new manifest and main.php.');
        }

        if (!mkdir($stage, 0700)) throw new RuntimeException('Could not create the package staging directory.');
        foreach ($files as $path => $source) {
            $destination = $stage . '/' . $path;
            $parent = dirname($destination);
            if ((!is_dir($parent) && !mkdir($parent, 0755, true)) || !copy($source, $destination)
                || !chmod($destination, fileperms($source) & 0777) || !touch($destination, filemtime($source))) {
                throw new RuntimeException("Could not stage package file: {$path}.");
            }
        }

        // Keep empty directories and retain permissions on surviving directories.
        foreach (array_keys($directories) as $path) {
            $destination = $stage . '/' . $path;
            if (!file_exists($destination) && is_dir(dirname($destination)) && !mkdir($destination, 0755)) {
                throw new RuntimeException("Could not stage package directory: {$path}.");
            }
        }
        foreach (array_reverse($directories, true) as $path => $source) {
            $destination = $stage . '/' . $path;
            if (is_dir($destination) && !chmod($destination, fileperms($source) & 0777)) {
                throw new RuntimeException("Could not restore directory permissions: {$path}.");
            }
        }
        if (!chmod($stage, fileperms($installed) & 0777)) {
            throw new RuntimeException('Could not set permissions on the staged package.');
        }
    }

    private static function release(string $root): array
    {
        if (!is_dir($root) || is_link($root) || !is_file($root . '/manifest.json') || is_link($root . '/manifest.json')
            || !is_file($root . '/main.php') || is_link($root . '/main.php')) {
            throw new RuntimeException("Expected a package release directory with manifest.json and main.php: {$root}.");
        }
        return ManifestReader::readFile($root . '/manifest.json');
    }

    private static function files(string $root, ?array &$directories = null): array
    {
        $root = rtrim($root, '/\\');
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $file) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            if (!self::validPath($path) || $file->isLink() || (!$file->isDir() && !$file->isFile())) {
                throw new RuntimeException("Unsupported package file: {$path}.");
            }
            if ($file->isFile()) {
                $files[$path] = $file->getPathname();
            } elseif ($directories !== null) {
                $directories[$path] = $file->getPathname();
            }
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    private static function validPath(string $path): bool
    {
        return $path !== self::METADATA && $path !== '' && !str_ends_with($path, '/')
            && !preg_match('~[\\\\\x00-\x1f:]|^/|//|(?:^|/)\.{1,2}(?:/|$)~', $path);
    }

    private static function validHash(mixed $hash): bool
    {
        return $hash === null || (is_string($hash) && preg_match('/^[a-f0-9]{64}$/D', $hash));
    }

    private static function hash(string $file): string
    {
        $hash = hash_file('sha256', $file);
        if ($hash === false) throw new RuntimeException("Could not hash package file: {$file}.");
        return $hash;
    }
}
