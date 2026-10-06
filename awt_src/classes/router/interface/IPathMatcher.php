<?php

namespace router\interface;

interface IPathMatcher
{
    /** Return captured parameters, or null when the path does not match. */
    public function match(string $pattern, string $path): ?array;
}
