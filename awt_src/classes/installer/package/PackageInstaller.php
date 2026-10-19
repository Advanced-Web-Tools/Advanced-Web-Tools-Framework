<?php

namespace installer\package;

use installer\events\PackageInstalledEvent;
use installer\interfaces\package\IExtractor;
use installer\interfaces\package\IPackageInstaller;
use installer\interfaces\package\IPackageMover;
use installer\interfaces\package\IPackageStorageTreeGenerator;
use package\dependency\Dependency;
use package\manifest\exceptions\ManifestReaderException;
use package\manifest\reader\ManifestReader;
use package\model\repository\interfaces\IPackageRepository;
use RuntimeException;
use vfs\resource\ResourceCache;
use vfs\transient\TransientStorageEntry;
use ZipArchive;

readonly class PackageInstaller implements IPackageInstaller
{
    public function __construct(
        private IExtractor                   $extractor,
        private IPackageMover                $mover,
        private IPackageStorageTreeGenerator $storageGenerator,
        private IPackageRepository           $packageRepository,
        private PackageAssetLinks            $assetLinks = new PackageAssetLinks(),
        private PackageDownload              $download = new PackageDownload(),
    ) {}

    /**
     * @inheritDoc
     * @throws ManifestReaderException
     * @throws \JsonException
     */
    public function install(bool $installDependencies = false): bool { return $this->apply(false, $installDependencies); }
    public function update(bool $installDependencies = false): bool { return $this->apply(true, $installDependencies); }

    private function apply(bool $updating, bool $installDependencies): bool
    {
        try {
            $this->extractor->extract();
            return $this->applyExtracted($this->extractor->getDestination(), $updating, $installDependencies);
        } finally {
            $this->cleanup($this->extractor->getDestination());
        }
    }

    private function applyExtracted(string $extractedBase, bool $updating, bool $installDependencies, array $chain = [], ?Dependency $expected = null): bool
    {
        global $eventDispatcher;
        $packageDir = is_file($extractedBase . DIRECTORY_SEPARATOR . 'manifest.json')
            ? $extractedBase : $this->findPackageDir($extractedBase);

        $manifestPath = $packageDir . DIRECTORY_SEPARATOR . 'manifest.json';

        $manifest = ManifestReader::readFile($manifestPath);
        if ($expected !== null && ($manifest['name'] !== $expected->name
            || !Dependency::matchesVersion($manifest['version'], $expected->version))) {
            throw new RuntimeException("Downloaded package does not match dependency: {$expected->name} {$expected->version}");
        }
        if (in_array($manifest['name'], $chain, true)) {
            throw new RuntimeException('Circular package dependency: ' . implode(' -> ', [...$chain, $manifest['name']]));
        }
        $chain[] = $manifest['name'];
        if (defined('AWT_VERSION') && (version_compare(AWT_VERSION, $manifest['minimum_awt_version'], '<')
            || (!empty($manifest['maximum_awt_version']) && version_compare(AWT_VERSION, $manifest['maximum_awt_version'], '>')))) {
            throw new RuntimeException('Package is incompatible with this AWT version.');
        }
        $installedPackage = $this->packageRepository->getPackage($manifest['name']);
        if ($updating && $installedPackage === null) {
            throw new RuntimeException("Package is not installed: {$manifest['name']}.");
        }
        if (!$updating && $installedPackage !== null) {
            throw new RuntimeException("Package is already installed: {$manifest['name']}. Use the update workflow.");
        }
        $verifyManifest  = new VerifyManifest($manifest);
        $verifyStructure = new VerifyStructure($packageDir);
        $preparer        = new PrepareInstallation($verifyManifest, $verifyStructure);

        if (!$preparer->prepare()->verify()) {
            return false;
        }

        foreach ($manifest['dependencies'] as $data) {
            $dependency = Dependency::fromArray($data);
            $installed = $this->packageRepository->getPackage($dependency->name);
            if ($installed !== null && Dependency::matchesVersion($installed['version'], $dependency->version)) continue;
            if (!$installDependencies) throw new RuntimeException("Required dependency is missing or incompatible: {$dependency->name} {$dependency->version}");
            if (trim($dependency->url) === '') throw new RuntimeException("No download URL provided for dependency: {$dependency->name} {$dependency->version}");
            if (in_array($dependency->name, $chain, true)) {
                throw new RuntimeException('Circular package dependency: ' . implode(' -> ', [...$chain, $dependency->name]));
            }
            $this->installDependency($dependency, $installed !== null, $chain);
        }

        // A later sibling may have updated a shared dependency to an incompatible version.
        foreach ($manifest['dependencies'] as $data) {
            $dependency = Dependency::fromArray($data);
            $installed = $this->packageRepository->getPackage($dependency->name);
            if ($installed === null || !Dependency::matchesVersion($installed['version'], $dependency->version)) {
                throw new RuntimeException("Required dependency is missing or incompatible: {$dependency->name} {$dependency->version}");
            }
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
        if ($updating) {
            $packageId = (int) $installedPackage['id'];
            // Optional metadata omitted from an update keeps its installed value.
            $forDb = array_merge($installedPackage, $forDb);
            // Keep resolved URLs until local asset registration resolves the new references.
            foreach (['icon', 'preview_image'] as $field) {
                $reference = $manifest[$field] ?? null;
                if (is_string($reference) && $reference !== ''
                    && !preg_match('~^(?:[a-z][a-z0-9+.-]*:|/)~i', $reference)
                    && !empty($installedPackage[$field])) {
                    $forDb[$field] = $installedPackage[$field];
                }
            }
            if (!$this->packageRepository->updatePackage($packageId, $forDb)) {
                throw new RuntimeException("Failed to update package '{$packageName}'.");
            }
        } else {
            $packageId = $this->packageRepository->newPackage($forDb);
        }

        if ($packageId === null) {
            throw new RuntimeException("Failed to register package '{$packageName}' in the database.");
        }

        $dataDir = $destination . DIRECTORY_SEPARATOR . 'data';
        if (is_dir($dataDir)) {
            $this->storageGenerator->setSource($dataDir);
            $this->storageGenerator->setBaseUrl('/awt_packages/' . rawurlencode(basename($destination)) . '/data');
            $this->storageGenerator->setPackageId($packageId);
            $this->storageGenerator->buildStorageTree()->generate();
            $this->storageGenerator->registerItems();
            $this->assetLinks->register($packageId, $dataDir, $manifest);
        }

        $hookFile = $destination . DIRECTORY_SEPARATOR . ($updating ? 'update.php' : 'install.php');
        if (is_file($hookFile)) {
            $hook = \object\ObjectHandler::createObjectFromFile($hookFile);
            if ($updating && $hook instanceof \package\install\interfaces\IPackageUpdate) {
                if (!$hook->update($packageId, $packageName)) throw new RuntimeException('Package update hook failed.');
            } elseif (!$updating && $hook instanceof \package\install\interfaces\IPackageInstall) {
                if (!$hook->postInstall($packageId, $packageName)) throw new RuntimeException('Package post-install hook failed.');
            } elseif ($updating && $hook instanceof \installer\interfaces\package\actions\IPostUpdate) {
                $hook->postUpdate($packageId, $packageName);
            } elseif (!$updating && $hook instanceof \installer\interfaces\package\actions\IPostInstall) {
                $hook->postInstall($packageId, $packageName);
            } else {
                throw new RuntimeException('Package hook does not implement the required install/update contract.');
            }
        }
        if ($updating && !ResourceCache::delete(basename($destination))) {
            throw new RuntimeException("Failed to clear resource cache for package '{$packageName}'.");
        }

        $e = $updating ? new \installer\events\PackageUpdatedEvent() : new PackageInstalledEvent();
        $e->setId($packageId);
        $e->setManifest($manifest);
        if ($eventDispatcher instanceof \event\EventDispatcher) $eventDispatcher->dispatch($e);

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
            if (is_dir($fullPath) && is_file($fullPath . DIRECTORY_SEPARATOR . 'manifest.json')) {
                return $fullPath;
            }
        }

        return $extractedBase;
    }

    private function installDependency(Dependency $dependency, bool $updating, array $chain): void
    {
        $archive = tempnam(TEMP, 'awt_dependency_');
        if ($archive === false) throw new RuntimeException('Failed to create temporary dependency archive.');
        $extractor = new Extractor(new ZipArchive(), new TransientStorageEntry(basename($archive), $archive), TEMP . 'dependency-');
        try {
            $this->download->download($dependency->url, $archive);
            $extractor->extract();
            if (!$this->applyExtracted($extractor->getDestination(), $updating, true, $chain, $dependency)) {
                throw new RuntimeException("Dependency installation failed: {$dependency->name}");
            }
        } finally {
            $this->cleanup($extractor->getDestination());
            if (is_file($archive)) unlink($archive);
        }
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }

        $scan = scandir($dir);
        foreach ($scan as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) && !is_link($path) ? $this->cleanup($path) : unlink($path);
        }

        rmdir($dir);
    }
}
