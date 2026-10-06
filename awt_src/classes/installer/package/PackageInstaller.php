<?php

namespace installer\package;

use installer\events\PackageInstalledEvent;
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
     * @throws \JsonException
     */
    public function install(): bool
    {
        global $eventDispatcher;
        $this->extractor->extract();
        $extractedBase = $this->extractor->getDestination();

        $packageDir = is_file($extractedBase . DIRECTORY_SEPARATOR . 'manifest.json')
            ? $extractedBase : $this->findPackageDir($extractedBase);

        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'manifest.json';

        $manifest = ManifestReader::validate(json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR));
        if (defined('AWT_VERSION') && (version_compare(AWT_VERSION, $manifest['minimum_awt_version'], '<')
            || (!empty($manifest['maximum_awt_version']) && version_compare(AWT_VERSION, $manifest['maximum_awt_version'], '>')))) {
            throw new RuntimeException('Package is incompatible with this AWT version.');
        }
        if ($this->packageRepository->getPackage($manifest['name']) !== null) {
            throw new RuntimeException("Package is already installed: {$manifest['name']}. Use the update workflow.");
        }
        foreach ($manifest['dependencies'] as $data) {
            $dependency = \package\dependency\Dependency::fromArray($data);
            $installed = $this->packageRepository->getPackage($dependency->name);
            if ($installed === null || !\package\dependency\Dependency::matchesVersion($installed['version'], $dependency->version)) {
                throw new RuntimeException("Required dependency is missing or incompatible: {$dependency->name} {$dependency->version}");
            }
        }

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
        $destination = rtrim(PACKAGES, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(' ', '', $packageName);

        if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
            throw new RuntimeException("Failed to create package directory: {$destination}");
        }

        $this->mover->setSource($packageDir);
        $this->mover->setDestination($destination);
        $this->mover->move();

        $manifestReader = new ManifestReader($packageName);
        $forDb = $manifestReader->getManifest();
        // Dependencies are persisted; startup never needs to reread the manifest.
        $packageId      = $this->packageRepository->newPackage($forDb);

        if ($packageId === null) {
            throw new RuntimeException("Failed to register package '{$packageName}' in the database.");
        }

        $dataDir = $destination . DIRECTORY_SEPARATOR . 'data';
        if (is_dir($dataDir)) {
            $this->storageGenerator->setSource($dataDir);
            $this->storageGenerator->setDestination(
                PACKAGE_STORAGE . $packageName
            );
            $this->storageGenerator->setBaseUrl('/packages/' . $packageName);
            $this->storageGenerator->setPackageId($packageId);
            $this->storageGenerator->buildStorageTree()->generate();
            $this->storageGenerator->registerItems();
        }

        $hookFile = $destination . DIRECTORY_SEPARATOR . 'install.php';
        if (is_file($hookFile)) {
            $hook = \object\ObjectHandler::createObjectFromFile($hookFile);
            if ($hook instanceof \packages\installer\interface\IPackageInstall) {
                if (!$hook->postInstall($packageId, $packageName)) throw new RuntimeException('Package post-install hook failed.');
            } elseif ($hook instanceof \installer\interfaces\package\actions\IPostInstall) {
                $hook->postInstall($packageId);
            }
        }
        $this->cleanup($extractedBase);

        $e = new PackageInstalledEvent();
        $e->setId($packageId);
        $e->setManifest($manifest);
        $eventDispatcher->dispatch($e);

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
