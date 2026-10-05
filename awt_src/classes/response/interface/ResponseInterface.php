<?php

namespace response\interface;

interface ResponseInterface
{
    public function status(int $code): static;
    public function message(string $message): static;
    public function header(string $name, string $value): static;
    public function data(array $data): static;
    public function file(string $path): static;

    /** Explicitly force a content type (overrides auto-resolution). */
    public function as(string $mimeType): static;

    /** Resolve sender from Accept / file extension and emit response. */
    public function send(): void;
}
