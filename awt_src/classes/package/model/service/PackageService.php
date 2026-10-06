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

    public function getPackageById(int $id): ?InstalledPackage
    {
        $data = $this->repository->getPackageById($id);
        return $data === null ? null : $this->mapPackage($data);
    }

    public function enablePackage(int $id): void { $this->setStatus($id, true); }
    public function disablePackage(int $id): void { $this->setStatus($id, false); }

    private function setStatus(int $id, bool $status): void
    {
        $package = $this->repository->getPackageById($id);
        if ($package === null) {
            throw new \OutOfBoundsException("Package with ID {$id} not found.");
        }
        if ((bool) $package['status'] === $status) return;
        if (!$this->repository->setStatus($id, $status)) {
            throw new \RuntimeException('Failed to persist package status.');
        }
    }

    private function mapPackages(array $data): array
    {
        return array_map(fn($item) => $this->mapPackage($item), $data);
    }

    private function mapPackage(array $data): InstalledPackage
    {
        $package = new InstalledPackage();
        $data['dependencies'] = $data['dependencies'] ?? [];
        if ($this->repository instanceof \database\DatabaseManager) {
            $package->useConnectionFrom($this->repository);
        }
        $package->hydrateRow($data, 'awt_package', 'id');
        $package->createDependencyCollection();
        return $package;
    }

}