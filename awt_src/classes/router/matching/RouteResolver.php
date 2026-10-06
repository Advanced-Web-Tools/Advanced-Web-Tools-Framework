<?php

namespace router\matching;

use router\http\HttpMethod;
use router\interface\IMethodPolicy;
use router\interface\IRequest;
use router\interface\IRouteResolver;

final class RouteResolver implements IRouteResolver
{
    public function __construct(private readonly IMethodPolicy $methods = new HttpMethod()) {}

    public function resolve(array $routes, IRequest $request): RouteResolution
    {
        $allowed = [];
        $fallback = null;
        foreach ($routes as $route) {
            $params = $route->match($request->path());
            if ($params === null) {
                continue;
            }
            $allowed = array_merge($allowed, $this->methods->allowed($route->getMethod()));
            if ($route->getMethod() === $request->method()) {
                return new RouteResolution($route, $params);
            }
            if ($this->methods->allows($route->getMethod(), $request->method())) {
                $fallback ??= new RouteResolution($route, $params);
            }
        }
        return $fallback ?? new RouteResolution(allowed: array_values(array_unique($allowed)));
    }
}
