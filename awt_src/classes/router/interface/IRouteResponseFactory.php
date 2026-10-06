<?php

namespace router\interface;

interface IRouteResponseFactory
{
    public function notFound(): \response\Response;
    public function methodNotAllowed(array $allowed): \response\Response;
    public function csrfRejected(): \response\Response;
}
