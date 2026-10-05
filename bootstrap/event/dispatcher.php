<?php
use event\EventDispatcher;
use router\events\EDynamicRouteListener;

global $routerManager;
global $eventDispatcher;

$dynamicRouteEvent = new EDynamicRouteListener();

$dynamicRouteEvent->addManager($routerManager);

$eventDispatcher->addListener("route.dynamic.add", $dynamicRouteEvent);

return $eventDispatcher;