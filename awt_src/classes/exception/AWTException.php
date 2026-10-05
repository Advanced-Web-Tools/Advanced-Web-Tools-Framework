<?php

namespace exception;

class AWTException extends ExceptionBase
{

    public function buildMessage(): string
    {
        return "AWT has encountered an error. While executing within the {$this->context->contextName}.";
    }
}