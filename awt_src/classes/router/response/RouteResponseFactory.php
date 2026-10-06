<?php

namespace router\response;

use response\Response;
use router\interface\IRouteResponseFactory;

final class RouteResponseFactory implements IRouteResponseFactory
{
    public function notFound(): Response
    {
        return Response::make(404, 'Not Found')->asHtml();
    }

    public function methodNotAllowed(array $allowed): Response
    {
        return Response::make(405, 'Method Not Allowed')
            ->header('Allow', implode(', ', $allowed))->asJson();
    }

    public function csrfRejected(): Response
    {
        return Response::make(403, 'Invalid CSRF token')->asJson();
    }
}
