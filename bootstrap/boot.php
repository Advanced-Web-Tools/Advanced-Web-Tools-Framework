<?php
require_once CONFIG . 'awt_config.php';
require_once FUNCTIONS . 'awt_autoLoader.fun.php';
require_once FUNCTIONS . "awt_getDomain.fun.php";
require_once FUNCTIONS . 'ErrorHandler.fun.php';

global $defaultRouters;
global $settings;
global $eventDispatcher;

$eventDispatcher = new \event\EventDispatcher();

global $shared;
global $loader;
global $routerManager;

spl_autoload_register();

$settings       = require __DIR__ . '/settings/settings.php';
$defaultRouters = require __DIR__ . '/routes/default.php';
$routerManager  = require __DIR__ . '/routes/manager.php';
$eventDispatcher = require __DIR__ . '/event/dispatcher.php';

define("HOSTNAME", getDomainName());

$shared["AWT"]["Settings"] = $settings;

if(defined("DEV")) {
    require_once  __DIR__ . '/dev.php';
}

$loader = require __DIR__ . '/packages/loader.php';

$routerManager = require __DIR__ . '/routes/router.php';

if(PHP_SAPI === 'cli')
    require_once __DIR__ . '/cli/handler.php';

