<?php

namespace router\http;

use router\interface\IRequest;
use router\interface\IRequestProvider;

final class GlobalsRequestProvider implements IRequestProvider
{
    public function current(): IRequest
    {
        $body = '';
        if (str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/x-www-form-urlencoded')) {
            $body = file_get_contents('php://input') ?: '';
        }
        return new Request($_SERVER, $_POST, $body);
    }
}
