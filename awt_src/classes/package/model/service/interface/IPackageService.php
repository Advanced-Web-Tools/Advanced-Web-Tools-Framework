<?php

namespace package\model\service\interface;

use package\model\InstalledPackage;
use package\model\repository\interfaces\IPackageRepository;

interface IPackageService
{
    public function __construct(
        IPackageRepository $repository
    );

    public function getActive(): array;

    public function getDisabled(): array;

    public function getInstalled(): array;

    public function getPackageById(int $id): ?InstalledPackage;

    public function enablePackage(int $id): void;

    public function disablePackage(int $id): void;

    public function getPackage(string $name): ?InstalledPackage;
}