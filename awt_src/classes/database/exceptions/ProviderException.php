<?php

namespace database\exceptions;

use database\interface\IProvider;
use exception\ExceptionBase;

class ProviderException extends ExceptionBase
{
    private string $provider;
    public function __construct(string $provider, ?\Throwable $previous = null)
    {
        $this->provider = $provider;
        parent::__construct($previous);
    }

    public function buildMessage(): string
    {
        return "Database error has occurred with provider {$this->provider} within {$this->context->contextName}.";
    }
}