<?php

use installer\package\Extractor;
use installer\package\PackageInstaller;
use installer\package\PackageMover;
use installer\package\PackageStorageTreeGenerator;
use package\facade\PackageFacade;
use vfs\storage\services\LocalFileSystemService;
use vfs\storage\StorageRepository;
use vfs\transient\TransientStorageEntry;

if (DEBUG && REMOTE_INSTALL_FOR_DEVS && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/dev/install') {
    if (!isset($_FILES["package"]) || DEV_SECRET != ($_POST["devSecret"] ?? null)) {
        die(WEB_NAME . ": Wrong dev secret, or missing file.");
    }

    try {
        $file = $_FILES['package'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Package upload failed.');
        }
        $installer = new PackageInstaller(
            new Extractor(new ZipArchive(), new TransientStorageEntry($file['name'], $file['tmp_name']), TEMP . 'dev_installer_'),
            new PackageMover('', PACKAGES),
            new PackageStorageTreeGenerator(new LocalFileSystemService(), new StorageRepository()),
            (new PackageFacade())->getRepository()
        );
        if (($_POST['action'] ?? 'install') === 'update') {
            if (!$installer->update()) throw new RuntimeException('Package update failed.');
        } else {
            $installer->execute();
        }
    } catch (Throwable $e) {
        die($e->getMessage());
    }

    die("Installed on " . WEB_NAME);
}
