<?php

namespace vfs\storage\exceptions\enums;
use Exception;
use Throwable;

class StorageException extends Exception
{
    public EStorageException $reason;
    public function __construct(EStorageException $reason, int $code = 0, ?Throwable $previous = null)
    {
        $this->reason = $reason;
        parent::__construct($reason->name, $code, $previous);
    }
}