<?php
global $settings;
global $eventDispatcher;
define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require_once  BASE_PATH . '/awt_data/config/awt_dirMap.php';
require_once  BASE_PATH . '/awt_data/config/awt_config.php';
require_once JOBS . 'loaders' . DIRECTORY_SEPARATOR . 'awt_autoLoader.php';
require_once JOBS . 'awt_settings.php';
require_once JOBS . "awt_domainBuilder.php";
require_once FUNCTIONS . 'awt_errorHandler.fun.php';
global $defaultRouters;
require_once  BASE_PATH .'/bootstrap/boot.php';
use event\EventDispatcher;
use packages\installer\PackageInstaller;
use packages\manager\loader\Loader;
use redirect\Redirect;
use router\events\EDynamicRouteListener;
use router\manager\RouterManager;
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

$packages = new Loader();
$shared["AWT"]["Settings"] = $settings;

$packages->sharedObjects = $shared;

$router = new RouterManager();
$eventDispatcher = new EventDispatcher();

$router->eventDispatcher = $eventDispatcher;

$dynamicRouteEvent = new EDynamicRouteListener();

$dynamicRouteEvent->addManager($router);

$eventDispatcher->addListener("route.dynamic.add", $dynamicRouteEvent);

$packages->eventDispatcher = $eventDispatcher;

if (Config::getConfig("AWT", "use packages")->getValue() == 'true') {
    try {
        $packages->load();
    } catch (Exception $e) {
        die($e->getMessage());
    }
}

$shared = $packages->sharedObjects;

$router->eventDispatcher = $packages->eventDispatcher;

foreach ($packages->routers as $route) {
    $router->loadRouters($route->getRouters());
}

foreach ($defaultRouters as $route) {
    $router->addRouter($route);
}

$router->loadRouters($defaultRouters);

$page = $router->startRouter();
$page->eventDispatcher = $eventDispatcher;
try {
    if ($page instanceof Redirect) {
        $redirect = $page->getRedirectTo();
        header("Location: $redirect");
        exit();
    }

    if($page instanceof \response\Response) {
        $page->send();
        exit();
    }

    $doc = $page->render();
} catch (Exception $e) {
    die("Internal error: " . $e->getMessage());
}

$redirect = new Redirect();
$redirect->setLast();

die($doc);