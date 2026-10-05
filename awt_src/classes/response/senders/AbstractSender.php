<?php

namespace response\senders;

use response\interface\SenderInterface;

abstract class AbstractSender implements SenderInterface
{
    // ── Shared helpers ────────────────────────────────────────────────────────

    protected function emitStatus(int $status): void
    {
        http_response_code($status);
    }

    protected function emitHeaders(array $headers): void
    {
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
    }

    protected function emitContentType(string $mime): void
    {
        header("Content-Type: $mime");
    }

    // ── File helpers ──────────────────────────────────────────────────────────

    protected function requireReadable(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("File not found: '$path'.");
        }
        if (!is_readable($path)) {
            throw new \RuntimeException("File not readable: '$path'.");
        }
    }

    protected function fileSize(string $path): int
    {
        $size = filesize($path);
        if ($size === false) {
            throw new \RuntimeException("Cannot determine file size: '$path'.");
        }
        return $size;
    }

    /** Stream a whole file in 8 KB chunks. */
    protected function streamFile(string $path): void
    {
        $this->requireReadable($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: '$path'.");
        }
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }

    /** Stream a byte range from a file (for Range requests). */
    protected function streamRange(string $path, int $start, int $length): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file: '$path'.");
        }
        fseek($handle, $start);
        $remaining = $length;
        while ($remaining > 0 && !feof($handle)) {
            $chunk      = min(8192, $remaining);
            $data       = fread($handle, $chunk);
            $remaining -= strlen($data);
            echo $data;
            flush();
        }
        fclose($handle);
    }

    /** Parse an HTTP Range header → [start, end]. */
    protected function parseRange(string $range, int $fileSize): array
    {
        if (!preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            throw new \RuntimeException("Malformed Range header: '$range'.");
        }
        $start = $m[1] !== '' ? (int) $m[1] : 0;
        $end   = $m[2] !== '' ? (int) $m[2] : $fileSize - 1;

        if ($start > $end || $end >= $fileSize) {
            http_response_code(416);
            header("Content-Range: bytes */$fileSize");
            throw new \RuntimeException("Range not satisfiable.");
        }
        return [$start, $end];
    }
}
