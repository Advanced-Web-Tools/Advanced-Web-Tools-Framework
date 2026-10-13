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
    header('Content-Type: text/plain; charset=utf-8');
    $postLimit = ini_parse_quantity(ini_get('post_max_size'));
    if ($postLimit > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postLimit) {
        http_response_code(413);
        die('Package request exceeds post_max_size (' . ini_get('post_max_size') . '). Configure the web PHP upload limits before retrying.');
    }
    $secret = $_POST['devSecret'] ?? null;
    if (!is_string($secret) || !hash_equals((string) DEV_SECRET, $secret)) {
        http_response_code(403);
        die(WEB_NAME . ': Wrong dev secret.');
    }
    $file = $_FILES['package'] ?? null;
    if (!is_array($file) || !isset($file['error']) || !is_int($file['error'])) {
        http_response_code(400);
        die('Missing or invalid package upload.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        [$status, $message] = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE => [413, 'Package exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . '). Configure the web PHP upload limits before retrying.'],
            UPLOAD_ERR_FORM_SIZE => [413, 'Package exceeds the upload form size limit.'],
            UPLOAD_ERR_PARTIAL => [400, 'Package was only partially uploaded. Retry the upload.'],
            UPLOAD_ERR_NO_FILE => [400, 'Missing package upload.'],
            UPLOAD_ERR_NO_TMP_DIR => [500, 'Upload temporary directory is missing.'],
            UPLOAD_ERR_CANT_WRITE => [500, 'Could not write the uploaded package to disk. Check free space and permissions.'],
            UPLOAD_ERR_EXTENSION => [500, 'A PHP extension stopped the package upload.'],
            default => [400, 'Package upload failed (error ' . $file['error'] . ').'],
        };
        http_response_code($status);
        die($message);
    }
    if (!isset($file['tmp_name'], $file['name']) || !is_string($file['tmp_name'])
        || !is_string($file['name']) || !is_uploaded_file($file['tmp_name'])) {
        http_response_code(400);
        die('Invalid package upload.');
    }

    try {
        set_time_limit(300);
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
        http_response_code(500);
        die($e->getMessage());
    }

    die("Installed on " . WEB_NAME);
}
