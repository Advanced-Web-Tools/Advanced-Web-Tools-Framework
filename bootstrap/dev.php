<?php

use packages\installer\PackageInstaller;
use setting\Config;

if (DEBUG && REMOTE_INSTALL_FOR_DEVS && $_SERVER['REQUEST_METHOD'] == 'POST' && $_SERVER['REQUEST_URI'] == '/dev/install') {
    if (!isset($_FILES["package"]) || DEV_SECRET != $_POST["devSecret"]) {
        die(WEB_NAME . ": Wrong dev secret, or missing file.");
    }

    $installer = new PackageInstaller($_FILES["package"]);

    try {
        $installer->
        setDataOwner("AWT")->
        uploadPackage()->
        extractPackage()->
        installPackage()->
        transferPackageFiles()->
        extractData()->
        cleanUp();
    } catch (Throwable $e) {
        die($e->getMessage());
    }

    die("Installed on " . Config::getConfig("AWT", "Website Name")->getValue());
}