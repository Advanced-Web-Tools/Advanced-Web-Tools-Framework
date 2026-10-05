<?php

namespace model\exceptions;

use exception\ExceptionBase;

class ModelCRUDException extends ExceptionBase
{

    public function buildMessage(): string
    {
        return "Model Exception: There was an error performing a CRUD operation. Within {$this->context->contextName}.";
    }
}