<?php

namespace router\interface;

interface IMethodPolicy
{
    public function normalize(string $method): string;
    public function isSafe(string $method): bool;
    public function allows(string $routeMethod, string $requestMethod): bool;
    public function allowed(string $routeMethod): array;
}
