<?php

namespace vfs\storage\interfaces;

use vfs\storage\StorageEntry;
use vfs\storage\enums\EOwnerType;

/**
 * Interface IStorageFacade
 *
 * Defines the public API for upload and download operations (ISP).
 * Sits in front of StorageManager and abstracts the full lifecycle
 * of receiving, registering, and serving files.
 */
interface IStorageFacade
{
    /**
     * Upload a single file from an HTTP request.
     *
     * @param array       $file      A single entry from $_FILES (e.g. $_FILES['avatar']).
     * @param EOwnerType  $ownerType The owner type to register the file under.
     * @param int|null    $ownerId   Optional owner ID to associate with the entry.
     * @param string|null $ownerName Optional owner name used to resolve the storage path.
     *                               Falls back to the event-dispatched context name when null.
     * @param string|null $middleware Optional middleware identifier stored on the entry.
     *
     * @return StorageEntry The persisted entry for the uploaded file.
     *
     * @throws \InvalidArgumentException if the file array is malformed or the upload failed.
     * @throws \RuntimeException         if the file cannot be moved or registered.
     */
    public function upload(
        array      $file,
        EOwnerType $ownerType,
        ?int       $ownerId   = null,
        ?string    $ownerName = null,
        ?string    $middleware = null
    ): StorageEntry;

    /**
     * Upload multiple files from an HTTP request.
     *
     * Accepts the normalised multi-file format produced by
     * {@see self::normaliseMultiFileArray()} or a plain array of individual
     * file arrays (each matching the shape of a single $_FILES entry).
     *
     * @param array       $files     Array of individual file arrays.
     * @param EOwnerType  $ownerType The owner type to register each file under.
     * @param int|null    $ownerId   Optional owner ID shared across all entries.
     * @param string|null $ownerName Optional owner name shared across all entries.
     * @param string|null $middleware Optional middleware identifier stored on each entry.
     *
     * @return StorageEntry[] Persisted entries in the same order as $files.
     *
     * @throws \InvalidArgumentException if any file entry is malformed.
     * @throws \RuntimeException         if any file cannot be moved or registered.
     */
    public function uploadMultiple(
        array      $files,
        EOwnerType $ownerType,
        ?int       $ownerId   = null,
        ?string    $ownerName = null,
        ?string    $middleware = null
    ): array;

    /**
     * Stream a stored file to the client as a download.
     *
     * Sends the appropriate headers (Content-Type, Content-Disposition,
     * Content-Length) and outputs the file contents, then terminates.
     *
     * @param int    $id       The StorageEntry ID to download.
     * @param string $disposition 'attachment' (force download) or 'inline' (browser preview).
     *
     * @throws \RuntimeException if the file does not exist on disk.
     */
    public function download(int $id, string $disposition = 'attachment'): void;

    /**
     * Normalise PHP's awkward multi-upload $_FILES structure into a plain
     * array of individual file arrays.
     *
     * PHP turns <input name="files[]" multiple> into a structure where each
     * key ('name', 'tmp_name', etc.) holds a sub-array indexed by file
     * position, rather than an array of per-file associative arrays.
     * This helper inverts that structure.
     *
     * Example input:
     * [
     *   'name'     => ['a.png', 'b.png'],
     *   'tmp_name' => ['/tmp/php001', '/tmp/php002'],
     *   'error'    => [0, 0],
     *   'size'     => [1024, 2048],
     *   'type'     => ['image/png', 'image/png'],
     * ]
     *
     * Example output:
     * [
     *   ['name' => 'a.png', 'tmp_name' => '/tmp/php001', 'error' => 0, 'size' => 1024, 'type' => 'image/png'],
     *   ['name' => 'b.png', 'tmp_name' => '/tmp/php002', 'error' => 0, 'size' => 2048, 'type' => 'image/png'],
     * ]
     *
     * @param array $filesEntry A raw $_FILES entry for a multi-file input.
     * @return array[]
     */
    public function normaliseMultiFileArray(array $filesEntry): array;
}