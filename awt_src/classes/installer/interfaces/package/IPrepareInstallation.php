<?php

namespace installer\interfaces\package;

interface IPrepareInstallation
{
    /**
     * Sets up the installation process.
     * @return self
     */
    public function prepare(): self;

    /**
     * Verifies the package structure, manifest and other necessary files/directories.
     * @return bool
     */
    public function verify(): bool;
}