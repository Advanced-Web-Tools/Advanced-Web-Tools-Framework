<?php

namespace installer\interfaces\package;

use packages\exceptions\RuntimeException;

interface IPackageInstaller
{

    /**
     * Installs the package.
     * @return bool
     */
    public function install(): bool;

    /**
     * Executes the installer actions.
     *
     * @throws RuntimeException on error.
     * @return bool
     */
    public function execute(): bool;
}