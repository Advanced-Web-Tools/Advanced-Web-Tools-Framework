<?php

if(file_exists(CONFIG . 'awt_db.php')) {
    require_once CONFIG . 'awt_db.php';
} else {
    define('DB_TYPE', '');
}

require_once CONFIG . 'awt_config.php';

if (DB_TYPE === '') {
    if (PHP_SAPI !== 'cli') {
        if (file_exists(CONFIG . 'awt_db.php')) {
            http_response_code(503);
            exit('The database configuration is incomplete. Review awt_db.php before continuing.');
        }
        require_once FUNCTIONS . 'awt_autoLoader.fun.php';
        (new \installer\web\WebInstaller())->handle();
        return;
    }

    require_once FUNCTIONS . 'awt_autoLoader.fun.php';
    require_once __DIR__ . '/cli/handler.php';
    return;
}

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

if (PHP_SAPI !== 'cli' && DEBUG && REMOTE_INSTALL_FOR_DEVS) {
    require_once  __DIR__ . '/dev.php';
}

global $cliHandler;
if (PHP_SAPI === 'cli') $cliHandler = new \cli\CLIHandler();
$loader = require __DIR__ . '/packages/loader.php';

$routerManager = require __DIR__ . '/routes/router.php';

if(PHP_SAPI === 'cli')
    require_once __DIR__ . '/cli/handler.php';
