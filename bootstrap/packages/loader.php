<?php
use packages\manager\loader\Loader;
global $shared;
global $eventDispatcher;

$loader = new Loader();
$loader->sharedObjects = $shared;
$loader->eventDispatcher = $eventDispatcher;

try {
    $loader->load();
    $shared = $loader->sharedObjects;
    $eventDispatcher = $loader->eventDispatcher;
} catch (Exception $e) {
    if(DEBUG) {
        throw($e);
    }
}


return $loader;