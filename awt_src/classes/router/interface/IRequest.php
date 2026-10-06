<?php

namespace router\interface;

interface IRequest
{
    public function path(): string;
    public function method(): string;
    public function csrfToken(string $name = '_token'): mixed;
}
