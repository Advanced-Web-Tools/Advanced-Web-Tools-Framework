<?php

namespace router\execution;

use context\events\RespondContextEvent;
use object\ObjectFactory;
use redirect\Redirect;
use response\Response;
use router\events\RouteEnterEvent;
use router\http\HttpMethod;
use router\interface\IRequest;
use router\interface\ICsrfValidator;
use router\interface\IRouteExecutor;
use router\interface\IRoute;
use router\security\SessionCsrfValidator;
use view\View;

/** Executes the route pipeline in security, middleware, event, action order. */
final class RouteExecutor implements IRouteExecutor
{
    public function __construct(
        private readonly ICsrfValidator $csrfValidator = new SessionCsrfValidator(),
        private readonly \router\interface\IMethodPolicy $methods = new HttpMethod(),
        private readonly \router\interface\IRouteResponseFactory $responses = new \router\response\RouteResponseFactory(),
    ) {}

    public function execute(IRoute $route, IRequest $request, array $params = []): View|Redirect|Response
    {
        if (!$this->methods->allows($route->getMethod(), $request->method())) {
            return $this->responses->methodNotAllowed($this->methods->allowed($route->getMethod()));
        }
        if ($route->requiresCsrf() && !$this->methods->isSafe($request->method())
            && !$this->csrfValidator->validate($request)) {
            return $this->responses->csrfRejected();
        }

        $route->getMiddleware()?->handle();
        $controller = $route->getController();
        if ($controller instanceof ObjectFactory) {
            $controller = $controller->create();
            $route->setController($controller);
        }
        $context = $controller->getContext();
        if ($context !== null) {
            $route->getEventDispatcher()->addListener('context.get', new RespondContextEvent($context));
        }
        $route->getEventDispatcher()->dispatch(new RouteEnterEvent($route->getPath(), $route->getAction(), $controller));
        $controller->viewName = $route->getAction();
        return $controller->{$route->getAction()}($params);
    }
}
