<?php

namespace vfs\resource\exceptions;

use Exception;
use vfs\resource\Resource;
use Throwable;

class ResourceException extends Exception
{

    private EResourceException $reason;
    private ?Resource $resource;

    public function __construct(
        EResourceException $reason,
        ?Resource          $resource = null,
        int                $code = 0,
        ?Throwable         $previous = null
    )
    {
        $this->reason = $reason;
        $this->resource = $resource;
        $message = $this->buildMessage();
        parent::__construct($message, $code, $previous);
    }

    public function getReason(): EResourceException
    {
        return $this->reason;
    }

    public function getResource(): ?Resource
    {
        return $this->resource;
    }

    private function buildMessage(): string
    {
        $prefix = "VFS Resource Exception:";

        $reasonMessage = match ($this->reason) {
            EResourceException::FileExistsButNotFound => "File exists in the resource node tree, but is not found on the filesystem.",
            EResourceException::FileNotFound => "File is not found in the resource node tree.",
            EResourceException::MissingContext => "No context is set. If you are trying to access a resource outside runtime API please use Resource::setContext().",
            EResourceException::MiddlewareBlocking => "Middleware is blocking access to this resource. Please check your middleware configuration or access it without VFS.",
        };

        $contextInfo = '';
        if ($this->resource !== null) {
            $context = $this->resource->getContext();

            $contextString = is_scalar($context) ? (string)$context : json_encode($context);
            $contextInfo = " | Context: {$contextString} | Access string: {$this->resource->access}";
        }

        return "{$prefix} {$reasonMessage}{$contextInfo}";
    }
}