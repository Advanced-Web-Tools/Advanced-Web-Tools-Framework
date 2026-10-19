<?php

namespace installer\interfaces\package;

use RuntimeException;

interface IPackageInstaller
{

    /**
     * Installs the package.
     * @param bool $installDependencies Recursively fetch missing/incompatible dependencies from manifest URLs.
     * @return bool
     */
    public function install(bool $installDependencies = false): bool;

    /** Update an installed package; dependency downloads are opt-in. */
    public function update(bool $installDependencies = false): bool;

    /**
     * Executes the installer actions.
     *
     * @throws RuntimeException on error.
     * @return bool
     */
    public function execute(): bool;
}
