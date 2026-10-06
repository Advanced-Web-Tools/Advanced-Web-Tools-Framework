<?php

namespace router\facade;

use controller\Controller;
use object\ObjectFactory;
use router\factory\RouteFactory;
use router\interface\IRoute;
use router\interface\IRouteFactory;

/** Static entry point; creation is delegated to a replaceable instance factory. */
final class Router
{
    private static ?IRouteFactory $factory = null;
    private function __construct() {}

    public static function setFactory(?IRouteFactory $factory): void
    {
        self::$factory = $factory;
    }

    public static function make(string $path, string $action, ObjectFactory|Controller $controller, bool $service = false): IRoute
    {
        return (self::$factory ??= new RouteFactory())->make($path, $action, $controller, $service);
    }
}
