<?php

namespace packages\exceptions;

use exception\ExceptionBase;
use packages\exceptions\enums\ERuntimeExceptionsMessage;

class RuntimeException extends ExceptionBase
{

    private ERuntimeExceptionsMessage $ERuntimeExceptionsMessage;

    public function __construct(ERuntimeExceptionsMessage $ERuntimeExceptionsMessage, ?\Throwable $previous = null)
    {
        $this->ERuntimeExceptionsMessage = $ERuntimeExceptionsMessage;
        parent::__construct($previous);
    }

    public function buildMessage(): string
    {
        return "{$this->context->contextName} runtime error. {$this->ERuntimeExceptionsMessage->value}";
    }
}