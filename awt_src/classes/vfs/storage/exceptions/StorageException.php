<?php

namespace vfs\storage\exceptions;
use Exception;
use vfs\storage\interface\IStorageEntryMiddleware;
use vfs\storage\interface\IStorageProvider;

class StorageException extends Exception
{
    private IStorageEntryMiddleware $middleware;
    private IStorageProvider $provider;
    private EStorageExceptions $reason;


}