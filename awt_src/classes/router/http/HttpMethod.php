<?php

namespace router\http;

final class HttpMethod implements \router\interface\IMethodPolicy
{
    public function normalize(string $method): string
    {
        $method = strtoupper(trim($method));
        if (!preg_match('/^[A-Z]+$/D', $method)) {
            throw new \InvalidArgumentException('Invalid HTTP method.');
        }
        return $method;
    }

    public function isSafe(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function allows(string $routeMethod, string $requestMethod): bool
    {
        return $routeMethod === $requestMethod || ($routeMethod === 'GET' && $requestMethod === 'HEAD');
    }

    public function allowed(string $routeMethod): array
    {
        return $routeMethod === 'GET' ? ['GET', 'HEAD'] : [$routeMethod];
    }
}
