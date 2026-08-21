<?php

namespace installer\interfaces\package\actions;

interface IPostUpdate
{
    /**
     * Executes post update actions.
     *
     * @param int $packageId
     * @return void
     */
    public function postUpdate(int $packageId): void;
}