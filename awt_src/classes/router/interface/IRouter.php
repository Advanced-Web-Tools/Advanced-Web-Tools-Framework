<?php

namespace router\interface;

/** Small registration contract shared by package runtimes and route managers. */
interface IRouter
{
    public function addRouter(IRoute $router): void;

    /** @return IRoute[] */
    public function getRouters(): array;
}
