<?php

namespace package\manifest\exceptions;

use exception\ExceptionBase;

class ManifestReaderException extends ExceptionBase
{

    public function __construct(
        public readonly string $name,
        public readonly string $path
    )
    {
        parent::__construct();
    }

    public function buildMessage(): string
    {
        return "Manifest Reader Exception: There was an error reading the manifest file. {$this->name} at {$this->path}}";
    }
}