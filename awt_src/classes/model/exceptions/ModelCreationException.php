<?php

namespace model\exceptions;

use exception\ExceptionBase;

class ModelCreationException extends ExceptionBase
{

    public function buildMessage(): string
    {
        return "Model Exception: There was an error creating the model. Within {$this->context->contextName}.";
    }
}