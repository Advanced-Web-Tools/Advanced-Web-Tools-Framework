<?php
namespace runtime;

use cli\CLIHandler;
use context\Context;
use context\events\RespondContextEvent;
use object\ObjectHandler;
use runtime\enums\ERuntimeFlags;
use runtime\interface\api\IRuntimeAPI;
use runtime\interface\IRuntime;
use runtime\interface\IRuntimeExecutor;
use vfs\resource\event\ContextSwitchEvent;

/** Runs package APIs through one lifecycle and one request-wide registry. */
final class RuntimeExecutor implements IRuntimeExecutor
{
    public array $sharedObjects = [];
    public array $passableInstances = [];
    public array $routers = [];
    public ?CLIHandler $CLIHandler = null;
    public ?\event\EventDispatcher $eventDispatcher = null;
    private IRuntime $runtime;
    private IRuntimeAPI $api;
    private array $linkStack = [];
    private ?\Closure $dependencyRunner = null;

    public function __construct(private readonly \runtime\interface\IRuntimeFlagHandler $flagHandler = new RuntimeFlagHandler()) {}
    public function setDependencyRunner(callable $runner): void { $this->dependencyRunner = \Closure::fromCallable($runner); }
    public function setRuntime(IRuntime $runtime): void { $this->runtime = $runtime; }
    public function initRuntime(): void
    {
        $this->activateContext($this->runtime);
        $this->api = $this->loadAPI($this->runtime->getRootPath() . 'main.php');
        $this->initialize($this->api, $this->runtime);
    }
    public function setupEnvironment(): void
    {
        $this->api->environmentSetup();
        $this->capture($this->api, $this->runtime);
        $this->waitForDependencies($this->api, $this->runtime);
    }
    public function setup(): void
    {
        $this->inject($this->api, $this->runtime);
        $this->api->setup();
        $this->capture($this->api, $this->runtime);
        $this->waitForDependencies($this->api, $this->runtime);
    }
    public function execute(): void { $this->executeMain($this->api, $this->runtime); }

    public function run(IRuntime $runtime): void
    {
        // Local variables survive nested dependency executions on this executor.
        $this->activateContext($runtime);
        $api = $this->loadAPI($runtime->getRootPath() . 'main.php');
        $this->runComponent($api, $runtime);
    }

    private function loadAPI(string $file): IRuntimeAPI
    {
        $api = ObjectHandler::createObjectFromFile($file);
        if (!$api instanceof IRuntimeAPI) {
            throw new \runtime\exceptions\RuntimeException("Runtime file must declare a package RuntimeAPI: {$file}");
        }
        return $api;
    }

    private function activateContext(IRuntime $runtime): void
    {
        $this->eventDispatcher ??= $runtime->getEventDispatcher();
        $runtime->setEventDispatcher($this->eventDispatcher);
        $dispatcher = $runtime->getEventDispatcher();
        $dispatcher->removeListeners('context.get');
        $dispatcher->addListener('context.get', new RespondContextEvent(
            new Context($runtime->getRootPath(), $runtime->getRuntimeName(), (string) $runtime->getRuntimeId())
        ));
    }

    private function initialize(IRuntimeAPI $api, IRuntime $runtime): void
    {
        $this->activateContext($runtime);
        $api->setInfo($runtime);
        // Each component owns its flags; they must not leak from its linker.
        $runtime->setFlags([]);
        $runtime->setWaitFor([]);
        $this->inject($api, $runtime);
    }

    private function inject(IRuntimeAPI $api, IRuntime $runtime): void
    {
        if ($this->eventDispatcher !== null) $runtime->setEventDispatcher($this->eventDispatcher);
        $runtime->setSharedRegistry($this->sharedObjects);
        $runtime->setPassable($this->passableInstances);
        if (property_exists($api, 'eventDispatcher')) {
            $api->eventDispatcher = $runtime->getEventDispatcher();
        }
        if ($this->CLIHandler !== null && property_exists($api, 'CLIHandler')) {
            $api->CLIHandler = $this->CLIHandler;
        }
    }

    private function waitForDependencies(IRuntimeAPI $api, IRuntime $runtime): void
    {
        $waits = $runtime->getWaitFor();
        foreach (array_unique($waits) as $name) {
            if ($this->dependencyRunner === null) {
                throw new \runtime\exceptions\RuntimeException("No dependency scheduler available for {$name}.");
            }
            ($this->dependencyRunner)($name);
        }
        // A dependency may have changed the context and request registries.
        if ($this->eventDispatcher !== null) $runtime->setEventDispatcher($this->eventDispatcher);
        $dispatcher = $runtime->getEventDispatcher();
        $dispatcher->removeListeners('context.get');
        $dispatcher->addListener('context.get', new RespondContextEvent(
            new Context($runtime->getRootPath(), $runtime->getRuntimeName(), (string) $runtime->getRuntimeId())
        ));
        $this->inject($api, $runtime);
    }

    public function prepareAPI(IRuntimeAPI $api, IRuntime $runtime): void
    {
        $this->initialize($api, $runtime);
        $api->environmentSetup();
        $this->capture($api, $runtime);
        $this->waitForDependencies($api, $runtime);
        $api->setup();
        $this->capture($api, $runtime);
        $this->waitForDependencies($api, $runtime);
    }

    private function capture(IRuntimeAPI $api, IRuntime $runtime): void
    {
        $runtime->setFlags($this->flagHandler->normalize($runtime->getFlags()));
        $this->sharedObjects = $runtime->getSharedRegistry();
        if (isset($api->eventDispatcher)) {
            $this->eventDispatcher = $api->eventDispatcher;
            $runtime->setEventDispatcher($this->eventDispatcher);
        }
    }

    private function runComponent(IRuntimeAPI $api, IRuntime $runtime): void
    {
        $this->prepareAPI($api, $runtime);
        $this->executeAPI($api, $runtime);
    }

    public function executeAPI(IRuntimeAPI $api, IRuntime $runtime): void
    {
        $this->executeMain($api, $runtime);
    }

    private function executeMain(IRuntimeAPI $api, IRuntime $runtime): void
    {
        $this->activateContext($runtime);
        $this->inject($api, $runtime);
        $switch = new ContextSwitchEvent();
        $switch->contextName = $runtime->getRuntimeName();
        $dispatcher = $runtime->getEventDispatcher();
        $dispatcher->removeListeners('vfs.context.request');
        $dispatcher->addListener('vfs.context.request', $switch);
        $api->main();
        $flags = $this->flagHandler->normalize($runtime->getFlags());
        $this->sharedObjects = $runtime->getSharedRegistry();
        if (isset($api->eventDispatcher)) {
            $this->eventDispatcher = $api->eventDispatcher;
            $runtime->setEventDispatcher($this->eventDispatcher);
            $dispatcher = $this->eventDispatcher;
        }
        if (in_array(ERuntimeFlags::CreatePassable, $flags, true)) {
            $this->passableInstances[$runtime->getRuntimeName()][get_class($api)] = $api;
        }
        if (in_array(ERuntimeFlags::Router, $flags, true)) {
            if (!method_exists($api, 'getRouters')) {
                throw new \runtime\exceptions\RuntimeException('Router runtimes must provide getRouters().');
            }
            $this->routers[] = $api;
        }
        if (in_array(ERuntimeFlags::RuntimeLinker, $flags, true)) {
            if (!$api instanceof \runtime\interface\api\IRuntimeLinkerCapabilities) {
                throw new \runtime\exceptions\RuntimeException('Linker runtimes must implement IRuntimeLinkerCapabilities.');
            }
            foreach ($api->getLinks() as $file) {
                $path = realpath($file);
                if ($path === false || !str_starts_with($path, realpath($runtime->getRootPath()) . DIRECTORY_SEPARATOR)) {
                    throw new \runtime\exceptions\RuntimeException("Linked runtime must be inside its package: {$file}");
                }
                if (isset($this->linkStack[$path])) {
                    throw new \runtime\exceptions\RuntimeException("Circular runtime link: {$file}");
                }
                $this->linkStack[$path] = true;
                try {
                    // A separate state prevents child flags/waits from overwriting its parent.
                    $child = Runtime::create($runtime->getPackage());
                    $child->rootPath = $runtime->getRootPath();
                    $child->setEventDispatcher($dispatcher);
                    $this->runComponent($this->loadAPI($path), $child);
                } finally {
                    unset($this->linkStack[$path]);
                    $this->activateContext($runtime);
                    $this->inject($api, $runtime);
                }
            }
        }
    }
}
