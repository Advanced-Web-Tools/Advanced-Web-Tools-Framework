<?php

namespace uninstaller\interfaces\actions;

interface IUninstall
{
    /**
     * Executes uninstall actions.
     * @param int $packageId
     * @return void
     */
    public function uninstall(int $packageId): void;
}