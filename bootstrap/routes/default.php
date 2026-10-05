<?php
use context\Context;
use router\Router;
use \bootstrap\controllers\StorageController;
require_once BASE_PATH . '/bootstrap/controllers/StorageController.php';

$controller = new StorageController();
$controller->setContext(new Context(BASE_PATH, "System", 0));

$request = $_SERVER['REQUEST_URI'];

$routes = [
    0 => new Router("/storage/{id}/{name}", "Index", $controller),
];

if(str_contains($request, "/awt_packages/")){
    preg_match('/\/awt_packages\/([^\/]+)(?:\/[^\/]+)*\/([^\/]+)$/', $request, $matches);
    $package  = $matches[1];
    $file     = $matches[2];
    $request = str_replace($package, "{package}", $request);
    $request = str_replace($file,    "{file}",    $request);
    $routes[] = new Router($request, "Resource", $controller);
}

return $routes;