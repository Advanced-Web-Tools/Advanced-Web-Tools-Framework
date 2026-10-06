<?php

namespace router\interface;

interface IRouteResolver
{
    /** @param IRoute[] $routes */
    public function resolve(array $routes, IRequest $request): \router\matching\RouteResolution;
}
