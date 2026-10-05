<?php
use event\EventDispatcher;
use package\model\repository\PackageRepository;
use package\model\service\PackageService;
use runtime\Runtime;
use runtime\RuntimeCreator;
use runtime\RuntimeExecutor;
use runtime\RuntimeLoader;
use runtime\RuntimeOrchestrator;

global $shared, $eventDispatcher, $cliHandler;

$service = new PackageService(new PackageRepository());
$runtimeLoader = new RuntimeLoader($service, new RuntimeCreator(Runtime::create()));
$runtimeLoader->load();
foreach ($runtimeLoader->getLoaded() as $runtime) $runtime->setEventDispatcher($eventDispatcher);

// Keep the loader's public registries available to the existing bootstrap and packages.
$loader = new RuntimeExecutor();
$loader->sharedObjects = $shared;
$loader->CLIHandler = $cliHandler ?? null;
$orchestrator = new RuntimeOrchestrator($loader, $runtimeLoader->getLoaded(), $service->getInstalled());
$orchestrator->run();
$shared = $loader->sharedObjects;
$eventDispatcher = $loader->eventDispatcher ?? $eventDispatcher;
return $loader;
