<?php

namespace cli\commands\PackageManager;

use installer\package\Extractor;
use installer\package\PackageInstaller;
use installer\package\PackageMover;
use installer\package\PackageStorageTreeGenerator;
use JsonException;
use package\manifest\exceptions\ManifestReaderException;
use package\model\repository\PackageRepository;
use vfs\storage\services\LocalFileSystemService;
use vfs\storage\StorageRepository;
use vfs\transient\TransientStorageEntry;

readonly class Install
{
    public function __construct(
        private string $packagePath
    ) {}


    /**
     * @throws ManifestReaderException
     */
    public function install(): void
    {
        $temp = TEMP . 'installer';

        $entry = new TransientStorageEntry("target", $this->packagePath);
        $extractor = new Extractor(new \ZipArchive(), $entry, $temp);
        $mover = new PackageMover($temp, PACKAGES);
        $treeGenerator = new PackageStorageTreeGenerator(
            new LocalFileSystemService(),
            new StorageRepository()
        );

        $repo = new PackageRepository();

        $packageInstaller = new PackageInstaller(
            $extractor,
            $mover,
            $treeGenerator,
            $repo
        );

        $packageInstaller->install();
    }
}