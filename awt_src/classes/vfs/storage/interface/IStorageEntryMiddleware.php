<?php

namespace vfs\storage\interface;

interface IStorageEntryMiddleware
{
    public function handle(): void;
    public function validate(): bool;
}