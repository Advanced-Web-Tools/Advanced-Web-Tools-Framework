<?php

namespace vfs\storage\exceptions;

use vfs\storage\exceptions\enums\ETransientStorageExceptionMessage;

class TransientStorageExceptions extends \RuntimeException
{
    public function __construct(
        ETransientStorageExceptionMessage $message,
        string                            $path,
        ?\Throwable                       $previous = null
    ) {
        parent::__construct(
            "Transient storage: {$message->value} file in {$path}",
            0,
            $previous
        );
    }
}