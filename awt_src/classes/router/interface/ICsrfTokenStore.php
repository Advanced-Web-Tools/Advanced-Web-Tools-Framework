<?php

namespace router\interface;

interface ICsrfTokenStore
{
    public function get(string $name): ?string;
    public function put(string $name, string $token): void;
}
