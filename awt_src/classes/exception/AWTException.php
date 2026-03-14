<?php

namespace exception;

class AWTException extends ExceptionBase
{

    public function buildMessage(): string
    {
        $message = "AWT has encountered an error. While executing within the {$this->context->contextName}.";
        return $message;
    }
}