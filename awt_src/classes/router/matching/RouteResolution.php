<?php

namespace router\matching;

use router\interface\IRoute;

final class RouteResolution
{
    public function __construct(
        public readonly ?IRoute $route = null,
        public readonly array $params = [],
        public readonly array $allowed = [],
    ) {}
}
