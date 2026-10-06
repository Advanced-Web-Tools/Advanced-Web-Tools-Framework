<?php

namespace router\factory;

use controller\Controller;
use object\ObjectFactory;
use router\execution\RouteExecutor;
use router\http\GlobalsRequestProvider;
use router\http\HttpMethod;
use router\interface\IMethodPolicy;
use router\interface\IPathMatcher;
use router\interface\IRequestProvider;
use router\interface\IRoute;
use router\interface\IRouteExecutor;
use router\interface\IRouteFactory;
use router\matching\SegmentPathMatcher;
use router\Router;

final class RouteFactory implements IRouteFactory
{
    private readonly IRouteExecutor $executor;

    public function __construct(
        private readonly IPathMatcher $pathMatcher = new SegmentPathMatcher(),
        ?IRouteExecutor $executor = null,
        private readonly IRequestProvider $requests = new GlobalsRequestProvider(),
        private readonly IMethodPolicy $methods = new HttpMethod(),
    ) {
        $this->executor = $executor ?? new RouteExecutor(methods: $this->methods);
    }

    public function make(string $path, string $action, ObjectFactory|Controller $controller, bool $service = false): IRoute
    {
        return new Router($path, $action, $controller, $service,
            $this->pathMatcher, $this->executor, $this->requests, $this->methods);
    }
}
