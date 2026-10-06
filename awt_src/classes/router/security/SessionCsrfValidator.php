<?php

namespace router\security;

use router\interface\ICsrfTokenManager;
use router\interface\ICsrfValidator;
use router\interface\IRequest;

final class SessionCsrfValidator implements ICsrfValidator
{
    public function __construct(private readonly ICsrfTokenManager $tokens = new Csrf()) {}

    public function validate(IRequest $request): bool
    {
        return $this->tokens->validateToken($request->csrfToken());
    }
}
