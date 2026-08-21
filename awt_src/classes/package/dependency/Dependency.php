<?php

namespace package\dependency;

readonly class Dependency
{
    public function __construct(
        public string $name,
        public string $version,
        public string $url
    ){}

    public static function fromArray(array $data): static
    {
        return new self($data['name'], $data['version'], $data['url']);
    }
}