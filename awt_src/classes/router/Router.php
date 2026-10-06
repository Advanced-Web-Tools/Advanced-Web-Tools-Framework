<?php
namespace router;

use controller\Controller;
use event\EventDispatcher;
use middleware\IMiddleware;
use object\ObjectFactory;
use redirect\Redirect;
use response\Response;
use view\View;
use router\execution\RouteExecutor;
use router\http\HttpMethod;
use router\interface\IRequest;
use router\interface\IRequestProvider;
use router\interface\IMethodPolicy;
use router\http\GlobalsRequestProvider;
use router\interface\IPathMatcher;
use router\interface\IRouteExecutor;
use router\matching\SegmentPathMatcher;

/**
 * Route definition and fluent configuration. Matching and execution are
 * delegated to replaceable collaborators; the existing route API is preserved.
 */
class Router implements \router\interface\IRoute
{
    /**
     * @var ?string $name
     * The name of the route, if defined.
     */
    public ?string $name;

    /**
     * @var string $path
     * The path pattern for the route.
     */
    public string $path;

    /**
     * @var string $action
     * The action name to be executed when the route is matched.
     */
    public string $action;

    /**
     * @var ?string $alias
     * An optional alias for the route.
     */
    public ?string $alias;

    /**
     * @var ObjectFactory|Controller $controller
     * The controller instance associated with the route.
     */
    public ObjectFactory|Controller $controller;

    /**
     * @var EventDispatcher $eventDispatcher
     * The event dispatcher to handle events for the route.
     */
    public EventDispatcher $eventDispatcher;

    private ?IMiddleware $middleware;

    public array $shared = [];

    /**
     * @var bool Service
     * Determines if this route is used for processing data or other type of jobs, and should be hidden from UI.
     */
    public bool $service = false;

    private string $method = 'GET';
    private bool $csrf = true;
    private readonly IPathMatcher $pathMatcher;
    private readonly IRouteExecutor $executor;

    /**
     * Router constructor.
     *
     * @param string $path The path pattern for the route.
     * @param string $action The action to be called.
     * @param ObjectFactory|Controller $controller The controller handling the action.
     * @param bool $service
     */
    public function __construct(
        string $path,
        string $action,
        ObjectFactory|Controller $controller,
        bool $service = false,
        ?IPathMatcher $pathMatcher = null,
        ?IRouteExecutor $executor = null,
        private readonly IRequestProvider $requests = new GlobalsRequestProvider(),
        private readonly IMethodPolicy $methods = new HttpMethod(),
    ) {
        $this->path = $path;
        $this->action = $action;
        $this->controller = $controller;
        $this->name = "";
        $this->alias = "";
        $this->middleware = null;
        $this->service = $service;

        $this->pathMatcher = $pathMatcher ?? new SegmentPathMatcher();
        $this->executor = $executor ?? new RouteExecutor(methods: $this->methods);
    }


    public function setMethod(string $method): self
    {
        $this->method = $this->methods->normalize($method);
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withoutCsrf(): self
    {
        $this->csrf = false;
        return $this;
    }

    public function requiresCsrf(): bool
    {
        return $this->csrf;
    }


    /**
     * Sets the name of the route.
     *
     * @param string $name The name to be set.
     * @return self The Router instance for method chaining.
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Sets an alias for the route.
     *
     * @param string $alias The alias to be set.
     * @return self The Router instance for method chaining.
     */
    public function setAlias(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * Adds an event dispatcher to the router.
     *
     * @param EventDispatcher $eventDispatcher The event dispatcher to be set.
     * @return self The Router instance for method chaining.
     */
    public function addEventDispatcher(EventDispatcher $eventDispatcher): self
    {
        $this->eventDispatcher = $eventDispatcher;
        return $this;
    }


    public function addMiddleware(IMiddleware $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function getMiddleware(): ?IMiddleware
    {
        return $this->middleware;
    }

    public function getName(): ?string { return $this->name; }
    public function getPath(): string { return $this->path; }
    public function getAction(): string { return $this->action; }
    public function getAlias(): ?string { return $this->alias; }
    public function isService(): bool { return $this->service; }
    public function getController(): ObjectFactory|Controller { return $this->controller; }
    public function getEventDispatcher(): EventDispatcher { return $this->eventDispatcher; }

    public function setController(ObjectFactory|Controller $controller): self
    {
        $this->controller = $controller;
        return $this;
    }

    /** Return captured path parameters, or null when the path does not match. */
    public function match(string $requestPath): ?array
    {
        return $this->pathMatcher->match($this->path, $requestPath);
    }

    /** Execute this route using the supplied request or the current HTTP request. */
    public function route(array $params = [], ?IRequest $request = null): View|Redirect|Response
    {
        return $this->executor->execute($this, $request ?? $this->requests->current(), $params);
    }
}
