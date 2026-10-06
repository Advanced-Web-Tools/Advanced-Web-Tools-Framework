<?php

namespace router\interface;

interface IRouteFactory
{
    public function make(string $path, string $action, \object\ObjectFactory|\controller\Controller $controller, bool $service = false): IRoute;
}
