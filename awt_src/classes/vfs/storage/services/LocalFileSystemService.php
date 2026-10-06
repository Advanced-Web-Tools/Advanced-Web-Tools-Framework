<?php

namespace vfs\storage\services;

use vfs\storage\interfaces\IFileSystemService;

class LocalFileSystemService implements IFileSystemService
{
    public function move(string $sourcePath, string $destinationPath): bool
    {
        if ($sourcePath === $destinationPath) return is_file($sourcePath);
        if (!is_file($sourcePath) || file_exists($destinationPath) || is_link($destinationPath)) return false;
        // link() creates the destination exclusively, preventing concurrent overwrites.
        if (@link($sourcePath, $destinationPath)) {
            if (@unlink($sourcePath)) return true;
            @unlink($destinationPath);
            return false;
        }
        // Cross-filesystem moves use the same exclusive destination creation.
        $mtime = filemtime($sourcePath);
        if (!$this->copy($sourcePath, $destinationPath)) return false;
        if ($mtime !== false) @touch($destinationPath, $mtime);
        if (@unlink($sourcePath)) return true;
        @unlink($destinationPath);
        return false;
    }

    public function copy(string $sourcePath, string $destinationPath): bool
    {
        if (!is_file($sourcePath)) return false;
        $source = @fopen($sourcePath, 'rb');
        if ($source === false) return false;
        $target = @fopen($destinationPath, 'xb');
        if ($target === false) { fclose($source); return false; }
        $expected = fstat($source)['size'];
        $written = @stream_copy_to_stream($source, $target);
        $flushed = @fflush($target);
        fclose($source);
        fclose($target);
        if ($written === false || $written !== $expected || !$flushed) {
            @unlink($destinationPath);
            return false;
        }
        clearstatcache(true, $destinationPath);
        return true;
    }

    public function delete(string $path): bool { return @unlink($path); }
    public function rename(string $sourcePath, string $destinationPath): bool { return $this->move($sourcePath, $destinationPath); }

    public function fileSize(string $path): int
    {
        clearstatcache(true, $path);
        $size = @filesize($path);
        if ($size === false) throw new \RuntimeException("Unable to determine file size for: {$path}");
        return $size;
    }

    public function lastModified(string $path): int
    {
        clearstatcache(true, $path);
        $mtime = @filemtime($path);
        if ($mtime === false) throw new \RuntimeException("Unable to determine last modified time for: {$path}");
        return $mtime;
    }

    public function exists(string $path): bool { clearstatcache(true, $path); return file_exists($path) || is_link($path); }

    public function makeDirectory(string $path, int $permissions = 0755, bool $recursive = true): bool
    {
        if (is_dir($path)) return true;
        @mkdir($path, $permissions, $recursive);
        return is_dir($path);
    }
}
