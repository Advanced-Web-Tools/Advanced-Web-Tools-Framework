<?php
namespace installer\interfaces\package;
use packages\exceptions\RuntimeException;
use vfs\transient\interfaces\ITransientStorageEntry;
use ZipArchive;
interface IExtractor
{
    /**
     * Extracts the zip file to the destination.
     * @return void
     */
    public function extract(): void;

    /**
     * Returns the zip archive.
     * @return ZipArchive
     */
    public function getZip(): ZipArchive;

    /**
     * Returns the target entry.
     * @return ITransientStorageEntry
     */
    public function getTarget(): ITransientStorageEntry;

    /**
     * Returns the destination path.
     * @return string
     */
    public function getDestination(): string;

    /**
     * Sets the target entry.
     * @param ITransientStorageEntry $target
     * @return void
     */
    public function setTarget(ITransientStorageEntry $target): void;

    /**
     * Sets the destination path.
     * @param string $destination
     * @return void
     */

    public function setDestination(string $destination): void;

    /**
     * Should throw an exception if the zip is invalid.
     *
     * @throws RuntimeException
     * @return void
     */
    public function zipVerify(): void;
}