<?php
use context\Context;
use router\Router;
use \bootstrap\controllers\StorageController;
require_once BASE_PATH . '/bootstrap/controllers/StorageController.php';

$controller = new StorageController();
$controller->setContext(new Context(BASE_PATH, "System", 0));

$request = $_SERVER['REQUEST_URI'] ?? '/';

$routes = [
    0 => new Router("/storage/{id}/{name}", "Index", $controller),
];

$requestPath = parse_url($request, PHP_URL_PATH);
if (is_string($requestPath) && str_starts_with($requestPath, '/awt_packages/')) {
    $segments = explode('/', substr($requestPath, strlen('/awt_packages/')));
    if (count($segments) >= 2) {
        $route = '/awt_packages/{package}';
        for ($i = 0; $i < count($segments) - 2; $i++) $route .= '/{resource' . $i . '}';
        $routes[] = new Router($route . '/{file}', 'Resource', $controller);
    }
}

return $routes;
