<?php

namespace package\facade;

use package\model\InstalledPackage;
use package\model\repository\interfaces\IPackageRepository;
use package\model\repository\PackageRepository;
use package\model\service\interface\IPackageService;
use package\model\service\PackageService;

/**
 * PackageFacade
 *
 * Handles installed package database operations.
 */
class PackageFacade
{
    private IPackageRepository $repository;
    private IPackageService $service;


    public function __construct()
    {
        $this->repository = new PackageRepository();
        $this->service = new PackageService($this->repository);
    }

    public function getService(): IPackageService
    {
        return $this->service;
    }

    public function getRepository(): IPackageRepository
    {
        return $this->repository;
    }

    public function getPackage(string $name): ?InstalledPackage
    {
        return $this->service->getPackage($name);
    }

    public function getActive(): array
    {
        return $this->service->getActive();
    }

    public function getDisabled(): array
    {
        return $this->service->getDisabled();
    }

}