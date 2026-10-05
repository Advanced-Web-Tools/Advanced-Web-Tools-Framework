<?php

namespace object\exceptions;

use exception\ExceptionBase;

class ObjectHandlerException extends ExceptionBase
{
    public function buildMessage(): string
    {
        return "Object Handler Exception: There was an error creating the object. Within {$this->context->contextName}.";
    }
}