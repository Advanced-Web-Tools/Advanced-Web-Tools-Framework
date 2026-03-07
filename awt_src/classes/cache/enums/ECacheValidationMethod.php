<?php

namespace cache\enums;

enum ECacheValidationMethod: string
{
    case TIME_MODIFIED = 'time';
    case HASH          = 'hash';
    case EXPIRY_LONG   = 'expiry_long';
    case EXPIRY_SHORT  = 'expiry_short';

    /**
     * Validates a single file against a stored hash or by its mtime.
     * Used for simple cache-file-only validation (no watched paths).
     *
     * @param string      $file        Absolute path to the cached file on disk.
     * @param string|null $storedHash  Hash persisted in the cache metadata (required for HASH case).
     */
    public function validate(string $file, ?string $storedHash = null): bool
    {
        if (!file_exists($file))
            return false;

        return match($this) {
            self::TIME_MODIFIED => filemtime($file) > time() - 3600,
            self::HASH          => $storedHash !== null && hash_file('xxh3', $file) === $storedHash,
            self::EXPIRY_LONG   => filemtime($file) > time() - 86400 * 30,
            self::EXPIRY_SHORT  => filemtime($file) > time() - 300,
        };
    }

    /**
     * Validates a single watched file against its stored snapshot entry.
     * Called by CacheControl when watched files or directories are registered.
     *
     * TIME_MODIFIED  — passes if the file's mtime has not changed since snapshot.
     * HASH           — passes if the file's xxh3 hash has not changed since snapshot.
     * EXPIRY_*       — not tied to watched paths; always passes so expiry-based
     *                  caches can still use watch targets without conflicting.
     *
     * @param string                          $file      Absolute path to the file being checked.
     * @param array{mtime: int, hash: string} $snapshot  The saved state for this file.
     */
    public function validateAgainstSnapshot(string $file, array $snapshot): bool
    {
        if (!file_exists($file))
            return false;

        return match($this) {
            self::TIME_MODIFIED => filemtime($file) === $snapshot['mtime'],
            self::HASH          => hash_file('xxh3', $file) === $snapshot['hash'],
            self::EXPIRY_LONG,
            self::EXPIRY_SHORT  => true,
        };
    }
}