<?php

namespace router\interface;

use redirect\Redirect;
use response\Response;
use router\interface\IRequest;
use router\interface\IRoute;
use view\View;

interface IRouteExecutor
{
    /** Enforce route policies before running middleware, events and the action. */
    public function execute(IRoute $route, IRequest $request, array $params = []): View|Redirect|Response;
}
