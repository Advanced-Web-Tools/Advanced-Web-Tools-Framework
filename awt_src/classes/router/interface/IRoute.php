<?php

namespace router\interface;

use controller\Controller;
use event\EventDispatcher;
use middleware\IMiddleware;
use object\ObjectFactory;
use redirect\Redirect;
use response\Response;
use view\View;

interface IRoute
{
    public function getName(): ?string;
    public function setName(string $name): self;
    public function getPath(): string;
    public function getAction(): string;
    public function getAlias(): ?string;
    public function setAlias(string $alias): self;
    public function isService(): bool;
    public function getController(): ObjectFactory|Controller;
    public function setController(ObjectFactory|Controller $controller): self;
    public function getEventDispatcher(): EventDispatcher;
    public function addEventDispatcher(EventDispatcher $eventDispatcher): self;
    public function getMiddleware(): ?IMiddleware;
    public function addMiddleware(IMiddleware $middleware): self;
    public function getMethod(): string;
    public function setMethod(string $method): self;
    public function requiresCsrf(): bool;
    public function withoutCsrf(): self;
    public function match(string $requestPath): ?array;
    public function route(array $params = [], ?IRequest $request = null): View|Redirect|Response;
}
