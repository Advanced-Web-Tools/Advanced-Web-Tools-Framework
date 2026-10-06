<?php

namespace router\interface;

interface ICsrfTokenManager
{
    public function token(string $name = '_token'): string;
    public function regenerate(string $name = '_token'): string;
    public function validateToken(mixed $token, string $name = '_token'): bool;
}
