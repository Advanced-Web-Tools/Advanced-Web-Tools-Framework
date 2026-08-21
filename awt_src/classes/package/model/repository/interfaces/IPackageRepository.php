<?php

namespace package\model\repository\interfaces;

use package\manifest\reader\interfaces\IManifestReader;

interface IPackageRepository
{
    public function getActive(): array;

    public function getDisabled(): array;

    public function getAll(): array;

    public function getPackage(string $name): ?array;

    public function newPackage(array $data): ?int;
}
