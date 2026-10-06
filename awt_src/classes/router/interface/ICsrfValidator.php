<?php

namespace router\interface;

use router\interface\IRequest;

interface ICsrfValidator
{
    public function validate(IRequest $request): bool;
}
