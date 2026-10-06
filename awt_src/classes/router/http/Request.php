<?php

namespace router\http;

/** A request snapshot, independent of PHP globals after construction. */
final class Request implements \router\interface\IRequest
{
    public function __construct(
        private readonly array $server = [],
        private readonly array $post = [],
        private readonly string $body = '',
    ) {}

    public function path(): string
    {
        return parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        $override = $this->post['_method'] ?? null;
        if ($method === 'POST' && is_string($override)
            && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
            return strtoupper($override);
        }
        return $method;
    }

    /** Return malformed values unchanged so validation can reject them. */
    public function csrfToken(string $name = '_token'): mixed
    {
        $token = $this->server['HTTP_X_CSRF_TOKEN'] ?? $this->post[$name] ?? null;
        if ($token === null && str_starts_with(strtolower($this->server['CONTENT_TYPE'] ?? ''), 'application/x-www-form-urlencoded')) {
            parse_str($this->body, $fields);
            $token = $fields[$name] ?? null;
        }
        return $token;
    }
}
