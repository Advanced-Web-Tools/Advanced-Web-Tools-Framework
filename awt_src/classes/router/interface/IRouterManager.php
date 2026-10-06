<?php

namespace router\interface;

use event\EventDispatcher;
use redirect\Redirect;
use response\Response;
use view\View;

interface IRouterManager extends IRouter
{
    public function addEventDispatcher(EventDispatcher $eventDispatcher): self;
    /** @param IRoute[] $routers */
    public function loadRouters(array $routers): void;
    /** @return array<string, IRoute> */
    public function getRoutes(): array;
    public function getRouteByName(string $name): ?IRoute;
    public function getRouteByPath(string $path, string $method = 'GET'): ?IRoute;
    public function startRouter(?IRequest $request = null): View|Redirect|Response;
}
