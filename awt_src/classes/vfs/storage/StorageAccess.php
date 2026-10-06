<?php

namespace vfs\storage;

use middleware\IMiddleware;

final class StorageAccess
{
    public static function authorize(StorageEntry $entry): void
    {
        $class = $entry->getMiddleware();
        if ($class === null || $class === '') return;
        try {
            if (!class_exists($class) || !is_subclass_of($class, IMiddleware::class)) {
                throw new \LogicException('Storage middleware must implement IMiddleware.');
            }
            $middleware = new $class();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Unable to resolve storage middleware.', previous: $e);
        }
        // Existing middleware may redirect, throw, or return void. Explicit false denies access.
        if ($middleware->handle() === false) throw new StorageAccessDenied('Storage access denied.');
    }
}
