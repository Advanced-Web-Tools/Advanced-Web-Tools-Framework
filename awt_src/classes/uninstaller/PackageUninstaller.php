<?php

namespace uninstaller;

use database\creator\table\TableRegistry;
use database\creator\table\TableSchemaProvider;
use database\DatabaseManager;
use database\provider\DatabaseProvider;
use package\facade\PackageFacade;
use uninstaller\cleanup\PackageFilesCleanup;
use uninstaller\cleanup\ResourceCleanup;
use uninstaller\cleanup\StorageCleanup;
use uninstaller\cleanup\TableCleanup;
use uninstaller\interfaces\IPackageCleanup;
use uninstaller\interfaces\IPackageUninstaller;
use vfs\storage\services\LocalFileSystemService;
use vfs\storage\StorageRepository;

/**
 * Self-wiring facade. Package and dependency records are left to the caller.
 * Every cleanup is attempted; false means getErrors() contains failures.
 */
class PackageUninstaller implements IPackageUninstaller
{
    private array $errors = [];
    private array $steps;
    private PackageFacade $packageFacade;

    /**
     * @param IPackageCleanup[]|null $steps Override or extend cleanup collaborators.
     */
    public function __construct(
        ?array $steps = null,
        ?PackageFacade $packageFacade = null,
        string $packageRoot = PACKAGES,
    ) {
        $this->packageFacade = $packageFacade ?? new PackageFacade();
        $this->steps = $steps ?? [
            new StorageCleanup(new StorageRepository(), new LocalFileSystemService()),
            new PackageFilesCleanup($packageRoot),
            new ResourceCleanup($packageRoot),
            new TableCleanup(new TableRegistry(new DatabaseManager()), new TableSchemaProvider(new DatabaseProvider())),
        ];
        foreach ($this->steps as $step) {
            if (!$step instanceof IPackageCleanup) {
                throw new \InvalidArgumentException('Cleanup steps must implement IPackageCleanup.');
            }
        }
    }

    public function uninstall(int $id): bool
    {
        $this->errors = [];
        try {
            if ($id <= 0) {
                throw new \InvalidArgumentException('Package ID must be positive.');
            }
            $package = $this->packageFacade->getPackageById($id);
            $name = $package->getName();
            if ($name === '' || $name === '.' || $name === '..' || strpbrk($name, "/\\\0") !== false) {
                throw new \InvalidArgumentException('Invalid package directory name.');
            }
            if (!$package->deleteModel()) throw new \RuntimeException('Failed to delete package record.');
        } catch (\Throwable $error) {
            $this->errors[] = 'Package lookup: ' . $error->getMessage();
            return false;
        }

        foreach ($this->steps as $step) {
            try {
                foreach ($step->clean($id, $name) as $error) {
                    $this->errors[] = get_class($step) . ': ' . $error;
                }
            } catch (\Throwable $error) {
                $this->errors[] = get_class($step) . ': ' . $error->getMessage();
            }
        }


        return $this->errors === [];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
