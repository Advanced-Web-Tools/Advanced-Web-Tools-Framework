<?php

namespace installer\interfaces\package;

interface IPackageStorageTreeGenerator
{

    /**
     * Registers all items from the storage tree into the database.
     * @return bool
     */
    public function registerItems(): bool;

    /**
     * Generates storage entries referencing files in the package's ``data/`` directory.
     * Files remain in place; no public storage copies are created.
     * @return self
     */
    public function generate(): self;

    /**
     * Returns the storage tree.
     * @return array
     */
    public function getStorageTree(): array;

    /**
     * Takes the ```/data/``` directory which is located in the package root and builds a storage tree recursively.
     * @return self
     */
    public function buildStorageTree(): self;

    /**
     * Sets the source directory (package's ``data/`` directory).
     * @param string $source
     * @return void
     */
    public function setSource(string $source): void;

    /**
     * Sets the package ID used when registering storage entries.
     * @param int $packageId
     * @return void
     */
    public function setPackageId(int $packageId): void;

    /**
     * Sets the base URL for public access to the package's data files.
     * @param string $baseUrl
     * @return void
     */
    public function setBaseUrl(string $baseUrl): void;
}