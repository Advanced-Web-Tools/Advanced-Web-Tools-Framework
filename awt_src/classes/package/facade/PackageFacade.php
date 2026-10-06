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


    public function __construct(?IPackageRepository $repository = null)
    {
        $this->repository = $repository ?? new PackageRepository();
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

    public function getPackageById(int $id): InstalledPackage
    {
        return $this->service->getPackageById($id) ?? throw new \OutOfBoundsException("Package with ID {$id} not found.");
    }

    public function getInstalled(): array { return $this->service->getInstalled(); }
    public function enablePackage(int $id): void { $this->service->enablePackage($id); }
    public function disablePackage(int $id): void { $this->service->disablePackage($id); }

    /** Remove code and resource records; purge also removes owned storage and tables. */
    public function removePackage(int $id, bool $purge = false): void
    {
        $steps = $purge ? null : [
            new \uninstaller\cleanup\PackageFilesCleanup(PACKAGES),
            new \uninstaller\cleanup\ResourceCleanup(PACKAGES),
        ];
        $uninstaller = new \uninstaller\PackageUninstaller($steps, $this);
        if (!$uninstaller->uninstall($id)) {
            throw new \RuntimeException(implode("\n", $uninstaller->getErrors()));
        }
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
