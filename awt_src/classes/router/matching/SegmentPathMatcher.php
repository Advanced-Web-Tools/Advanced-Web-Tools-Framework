<?php

namespace router\matching;

use router\interface\IPathMatcher;

final class SegmentPathMatcher implements IPathMatcher
{
    public function match(string $pattern, string $path): ?array
    {
        $segments = explode('/', $pattern);
        $requested = explode('/', $path);
        if (count($segments) !== count($requested)) {
            return null;
        }

        $params = [];
        foreach ($segments as $index => $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $params[trim($segment, '{}')] = $requested[$index];
            } elseif ($segment !== $requested[$index]) {
                return null;
            }
        }
        return $params;
    }
}
