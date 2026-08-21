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
     * Generates the storage tree for the package in ``awt_data/public/packages/<name>`` directory.
     * Also copies all files and directories from the ``data`` directory of the package to the ``awt_data/public/packages/<name>`` directory.
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
     * Sets the destination directory (public packages directory).
     * @param string $destination
     * @return void
     */
    public function setDestination(string $destination): void;

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