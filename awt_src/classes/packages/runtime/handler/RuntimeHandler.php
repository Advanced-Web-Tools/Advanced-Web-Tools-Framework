<?php
namespace packages\runtime\handler;

use cli\CLIHandler;
use context\events\RespondContextEvent;
use event\EventDispatcher;
use object\ObjectHandler;
use packages\runtime\api\RuntimeAPI;
use packages\runtime\interface\IRuntime;
use packages\runtime\Runtime;
use runtime\compatibility\LegacyRuntimeAdapter;
use runtime\RuntimeExecutor;
use vfs\resource\event\ContextSwitchEvent;

/** Compatibility facade; all execution is delegated to the new executor. */
class RuntimeHandler extends RuntimeExceptions
{
    protected RuntimeAPI $runtime;
    protected \runtime\Runtime $runtimeState;
    protected RuntimeExecutor $executor;
    public array $passableInstances = [];
    public array $routers = [];
    public array $sharedObjects = [];
    public ContextSwitchEvent $vfsContext;
    public RespondContextEvent $respondContext;
    public ?CLIHandler $CLIHandler = null;
    public EventDispatcher $eventDispatcher;

    public function __construct()
    {
        $this->executor = new RuntimeExecutor();
        $this->vfsContext = new ContextSwitchEvent();
    }
    protected function configureExecutor(): void
    {
        $this->executor->sharedObjects = $this->sharedObjects;
        $this->executor->passableInstances = $this->passableInstances;
        $this->executor->routers = $this->routers;
        $this->executor->eventDispatcher = $this->eventDispatcher;
        $this->executor->CLIHandler = $this->CLIHandler;
    }
    protected function syncExecutor(): void
    {
        $this->sharedObjects = $this->executor->sharedObjects;
        $this->passableInstances = $this->executor->passableInstances;
        $this->routers = $this->executor->routers;
        $this->eventDispatcher = $this->executor->eventDispatcher ?? $this->eventDispatcher;
    }
    public function runtimeCreator(RuntimeAPI $package, Runtime $origin): IRuntime
    {
        $this->configureExecutor();
        $this->runtime = $package;
        $this->runtimeState = \runtime\Runtime::create(LegacyRuntimeAdapter::installedPackage($origin));
        $this->runtimeState->setEventDispatcher($this->eventDispatcher);
        $this->executor->prepareAPI($package, $this->runtimeState);
        $this->syncExecutor();
        return $package;
    }
    public function execute(): void
    {
        $this->executor->executeAPI($this->runtime, $this->runtimeState);
        $this->syncExecutor();
    }
    public function flagHandler(): void
    {
        $this->runtimeState->setFlags((new \runtime\RuntimeFlagHandler())->normalize($this->runtime->configurationFlags));
    }
    public function loadLinked(RuntimeAPI $runtime): array
    {
        $objects = [];
        foreach ($runtime->links ?? [] as $file) {
            $object = ObjectHandler::createObjectFromFile($file);
            if (!$object instanceof RuntimeAPI) throw new \RuntimeException("Missing legacy RuntimeAPI in {$file}");
            $objects[] = $object;
        }
        return $objects;
    }
    protected function resetRuntimeHandler(): void
    {
        // Request-wide shared/passable registries deliberately survive package boundaries.
    }
}
