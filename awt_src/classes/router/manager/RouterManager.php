<?php

namespace router\manager;

use event\EventDispatcher;
use redirect\Redirect;
use response\Response;
use router\http\GlobalsRequestProvider;
use router\interface\IRequest;
use router\interface\IRequestProvider;
use router\interface\IRoute;
use router\interface\IRouterManager;
use router\interface\IRouteResolver;
use router\interface\IRouteResponseFactory;
use router\matching\RouteResolver;
use router\response\RouteResponseFactory;
use view\View;

/** Registers route contracts and coordinates replaceable resolution and responses. */
final class RouterManager implements IRouterManager
{
    /** @var array<string, IRoute> */
    private array $routesName = [];
    public EventDispatcher $eventDispatcher;

    public function __construct(
        private readonly IRequestProvider $requests = new GlobalsRequestProvider(),
        private readonly IRouteResolver $resolver = new RouteResolver(),
        private readonly IRouteResponseFactory $responses = new RouteResponseFactory(),
        ?EventDispatcher $eventDispatcher = null,
    ) {
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
    }

    public function addEventDispatcher(EventDispatcher $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;
        foreach ($this->routesName as $route) {
            $route->addEventDispatcher($eventDispatcher);
        }
        return $this;
    }

    public function addRouter(IRoute $router): void
    {
        $name = $router->getName();
        if ($name === null || trim($name) === '') {
            $index = count($this->routesName);
            while (isset($this->routesName[(string) $index])) {
                $index++;
            }
            $router->setName((string) $index);
        }
        $router->addEventDispatcher($this->eventDispatcher);
        $this->routesName[$router->getName()] = $router;
    }

    public function loadRouters(array $routers): void
    {
        foreach ($routers as $router) {
            $this->addRouter($router);
        }
    }

    public function getRoutes(): array { return $this->routesName; }
    public function getRouters(): array { return $this->getRoutes(); }

    public function getRouteByName(string $name): ?IRoute
    {
        return $this->routesName[$name] ?? null;
    }

    public function getRouteByPath(string $path, string $method = 'GET'): ?IRoute
    {
        foreach ($this->routesName as $route) {
            if ($route->getPath() === $path && $route->getMethod() === strtoupper($method)) {
                return $route;
            }
        }
        return null;
    }

    public function startRouter(?IRequest $request = null): View|Redirect|Response
    {
        $request ??= $this->requests->current();
        $match = $this->resolver->resolve($this->routesName, $request);
        if ($match->route !== null) {
            return $match->route->route($match->params, $request);
        }
        return $match->allowed !== []
            ? $this->responses->methodNotAllowed($match->allowed)
            : $this->responses->notFound();
    }
}
