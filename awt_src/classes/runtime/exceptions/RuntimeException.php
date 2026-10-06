<?php
namespace runtime\exceptions;

use runtime\enums\ERuntimeExceptionsMessage;

/** Diagnostics can be raised before a package context exists. */
class RuntimeException extends \RuntimeException
{
    public function __construct(string|ERuntimeExceptionsMessage $message, ?\Throwable $previous = null)
    {
        parent::__construct($message instanceof ERuntimeExceptionsMessage ? $message->value : $message, 0, $previous);
    }
}
