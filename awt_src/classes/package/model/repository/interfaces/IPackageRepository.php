<?php

namespace package\model\repository\interfaces;


interface IPackageRepository
{
    public function getActive(): array;

    public function getDisabled(): array;

    public function getAll(): array;

    public function getPackage(string $name): ?array;

    public function getPackageById(int $id): ?array;

    public function setStatus(int $id, bool $status): bool;

    public function updatePackage(int $id, array $data): bool;

    public function newPackage(array $data): ?int;
}
