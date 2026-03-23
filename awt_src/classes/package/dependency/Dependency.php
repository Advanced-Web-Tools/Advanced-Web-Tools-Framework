<?php

namespace package\dependency;

class Dependency
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $url
    ){}

    public static function __fromArray(array $data): static
    {
        return new self($data['name'], $data['version'], $data['url']);
    }
}