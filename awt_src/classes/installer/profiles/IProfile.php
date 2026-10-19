<?php

namespace installer\profiles;

interface IProfile
{
    public function id(): string;
    public function name(): string;
    public function description(): string;
    public function icon(): string;
    public function requiresAdmin(): bool;
    /** Root archive URLs. Their manifest dependencies are installed recursively. */
    public function packages(): array;
}
