<?php

namespace uninstaller\cleanup;

use uninstaller\interfaces\IPackageCleanup;

class PackageFilesCleanup implements IPackageCleanup
{
    private array $scanned = ['dir' => [], 'file' => []];

    public function __construct(private readonly string $packageRoot) {}

    public function clean(int $id, string $name): array
    {
        $this->scanned = ['dir' => [], 'file' => []];
        $root = realpath($this->packageRoot);
        if ($root === false || !is_dir($root)) {
            return ['Package root directory does not exist.'];
        }
        if ($name === '' || $name === '.' || $name === '..' || strpbrk($name, "/\\\0") !== false) {
            return ['Invalid package directory name.'];
        }
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($path) && !is_link($path)) {
            return [];
        }
        if (is_link($path) || !is_dir($path)) {
            return @unlink($path) ? [] : ["Failed to delete: {$path}"];
        }
        $errors = [];
        $this->scanDirectory($path, $errors);
        foreach ($this->scanned['file'] as $file) {
            if (!@unlink($file)) {
                $errors[] = "Failed to delete file: {$file}";
            }
        }
        foreach (array_reverse($this->scanned['dir']) as $dir) {
            if (!@rmdir($dir)) {
                $errors[] = "Failed to delete directory: {$dir}";
            }
        }
        if (!@rmdir($path)) {
            $errors[] = "Failed to delete package directory: {$path}";
        }
        return $errors;
    }

    private function scanDirectory(string $dir, array &$errors): void
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            $errors[] = "Failed to scan directory: {$dir}";
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            // Unlink links themselves, including directory and dangling links.
            if (!is_link($fullPath) && is_dir($fullPath)) {
                $this->scanned['dir'][] = $fullPath;
                $this->scanDirectory($fullPath, $errors);
            } else {
                $this->scanned['file'][] = $fullPath;
            }
        }
    }
}
