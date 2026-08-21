<?php

namespace installer\interfaces\package\actions;

interface IPostInstall
{
    /**
     * Executes post install actions.
     *
     * @param int $packageId
     * @return void
     */
    public function postInstall(int $packageId): void;
}