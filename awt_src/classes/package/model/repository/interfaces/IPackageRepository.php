<?php

namespace package\model\repository\interfaces;

interface IPackageRepository
{
    public function getActive(): array;

    public function getDisabled(): array;

    public function getAll(): array;

    public function getPackage(string $name): ?array;
}
