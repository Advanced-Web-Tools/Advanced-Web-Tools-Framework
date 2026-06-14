<?php

namespace installer\package;

use installer\interfaces\package\IExtractor;
use installer\interfaces\package\IPackageInstaller;
use installer\interfaces\package\IPackageMover;
use installer\interfaces\package\IPackageStorageTreeGenerator;
use package\manifest\exceptions\ManifestReaderException;
use package\manifest\reader\ManifestReader;
use package\model\repository\interfaces\IPackageRepository;
use RuntimeException;

readonly class PackageInstaller implements IPackageInstaller
{
    public function __construct(
        private IExtractor                   $extractor,
        private IPackageMover                $mover,
        private IPackageStorageTreeGenerator $storageGenerator,
        private IPackageRepository           $packageRepository,
    ) {}

    /**
     * @inheritDoc
     * @throws ManifestReaderException
     */
    public function install(): bool
    {
        $this->extractor->extract();
        $extractedBase = $this->extractor->getDestination();

        $packageDir = $this->findPackageDir($extractedBase);

        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'manifest.json';
        $manifest     = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

        if ($manifest === null) {
            return false;
        }

        $verifyManifest  = new VerifyManifest($manifest);
        $verifyStructure = new VerifyStructure($packageDir);
        $preparer        = new PrepareInstallation($verifyManifest, $verifyStructure);

        if (!$preparer->prepare()->verify()) {
            return false;
        }

        $packageName = $manifest['name'];
        $destination = rtrim(PACKAGES, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $packageName;

        if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new RuntimeException("Failed to create package directory: {$destination}");
        }

        $this->mover->setSource($packageDir);
        $this->mover->setDestination($destination);
        $this->mover->move();

        $manifestReader = new ManifestReader($packageName);
        $packageId      = $this->packageRepository->newPackage($manifestReader);

        if ($packageId === null) {
            throw new RuntimeException("Failed to register package '{$packageName}' in the database.");
        }

        $dataDir = $destination . DIRECTORY_SEPARATOR . 'data';
        if (is_dir($dataDir)) {
            $this->storageGenerator->setSource($dataDir);
            $this->storageGenerator->setDestination(
                DATA . 'public' . DIRECTORY_SEPARATOR . 'packages' . DIRECTORY_SEPARATOR . $packageName
            );
            $this->storageGenerator->setBaseUrl('/packages/' . $packageName);
            $this->storageGenerator->setPackageId($packageId);
            $this->storageGenerator->buildStorageTree()->generate();
            $this->storageGenerator->registerItems();
        }

        $this->cleanup($extractedBase);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function execute(): bool
    {
        if (!$this->install()) {
            throw new RuntimeException("Package installation failed.");
        }

        return true;
    }

    private function findPackageDir(string $extractedBase): string
    {
        $scan = scandir($extractedBase);
        foreach ($scan as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $fullPath = $extractedBase . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($fullPath)) {
                return $fullPath;
            }
        }

        return $extractedBase;
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $scan = scandir($dir);
        foreach ($scan as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->cleanup($path) : unlink($path);
        }

        rmdir($dir);
    }
}
