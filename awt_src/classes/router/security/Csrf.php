<?php

namespace router\security;

use router\interface\ICsrfTokenManager;
use router\interface\ICsrfTokenStore;

final class Csrf implements ICsrfTokenManager
{
    public function __construct(private readonly ICsrfTokenStore $store = new SessionTokenStore()) {}

    public function token(string $name = '_token'): string
    {
        $token = $this->store->get($name);
        return $token !== null && $token !== '' ? $token : $this->regenerate($name);
    }

    public function regenerate(string $name = '_token'): string
    {
        $token = bin2hex(random_bytes(32));
        $this->store->put($name, $token);
        return $token;
    }

    public function validateToken(mixed $token, string $name = '_token'): bool
    {
        $expected = $this->store->get($name);
        return $expected !== null && $expected !== '' && is_string($token)
            && hash_equals($expected, $token);
    }
}
