<?php

namespace uninstaller\interfaces;

interface IPackageUninstaller
{
    public function uninstall(int $id): bool;

    /** @return string[] Failures from the most recent uninstall attempt. */
    public function getErrors(): array;
}
