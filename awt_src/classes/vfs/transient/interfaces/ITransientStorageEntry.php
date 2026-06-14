<?php

namespace vfs\transient\interfaces;

use vfs\transient\enums\ETransientType;

interface ITransientStorageEntry
{
    public function refresh(): self;

    public function loadContent(): self;

    public function write(string|array $content): self;

    public function delete(): bool;

    public function getName(): string;

    public function getPath(): string;

    public function getLastModified(): int;

    public function __toString(): string;
}
