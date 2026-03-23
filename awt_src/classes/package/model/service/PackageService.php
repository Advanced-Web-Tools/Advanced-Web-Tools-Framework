<?php

namespace package\model\service;

use package\model\InstalledPackage;
use package\model\repository\interfaces\IPackageRepository;
use package\model\service\interface\IPackageService;

class PackageService implements IPackageService
{
    public function __construct(
        private readonly IPackageRepository $repository
    )
    {}

    public function getActive(): array
    {
        $res = $this->repository->getActive();
        return $this->mapPackages($res);
    }

    public function getDisabled(): array
    {
        $res = $this->repository->getDisabled();
        return $this->mapPackages($res);
    }


    public function getInstalled(): array
    {
        $res = $this->repository->getAll();
        return $this->mapPackages($res);
    }

    public function getPackage(string $name): ?InstalledPackage
    {
        $res = $this->repository->getPackage($name);

        if (!$res) return null;

        return $this->mapPackage($res);
    }

    private function mapPackages(array $data): array
    {
        return array_map(fn($item) => $this->mapPackage($item), $data);
    }

    private function mapPackage(array $data): InstalledPackage
    {
        $package = new InstalledPackage();
        $package->fromArray($data);
        $package->createDependencyCollection();
        return $package;
    }

}