<?php
use redirect\Redirect;
use response\Response;

global $loader;
global $defaultRouters;
global $routerManager;
global $eventDispatcher;

$routerManager->addEventDispatcher($eventDispatcher);

foreach ($loader->routers as $router) {
    $routerManager->loadRouters($router->getRouters());
}

foreach ($defaultRouters as $route) {
    $routerManager->addRouter($route);
}

if (PHP_SAPI === 'cli')
    return $routerManager;

// HEAD runs the normal route pipeline and sends headers, but never a body.
$request = (new \router\http\GlobalsRequestProvider())->current();
if ($request->method() === 'HEAD') {
    ob_start(static fn(string $body): string => '');
}

$page = $routerManager->startRouter($request);

try {
    if ($page instanceof Redirect) {
        $redirect = $page->getRedirectTo();
        header("Location: $redirect");
        exit();
    }

    if($page instanceof Response) {
        $page->send();
        exit();
    }

    $doc = $page->render();
} catch (Exception $e) {
    die("Internal error: " . $e->getMessage());
}

echo $doc;

$redirect = new Redirect();
$redirect->setLast();

return $routerManager;
