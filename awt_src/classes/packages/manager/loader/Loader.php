<?php
namespace packages\manager\loader;

use context\Context;
use context\events\RespondContextEvent;
use object\ObjectHandler;
use packages\manager\PackageManager;
use packages\runtime\api\RuntimeAPI;
use packages\runtime\handler\RuntimeHandler;
use packages\runtime\Runtime;
use runtime\compatibility\LegacyRuntimeAdapter;
use runtime\RuntimeOrchestrator;

/** Keeps the legacy loader entry point and registries on the new scheduler. */
final class Loader extends RuntimeHandler
{
    public array $loadingList = [];
    public array $active = [];
    public array $loaded = [];
    public array $waitListTrack = [];
    private array $installed = [];

    public function __construct()
    {
        parent::__construct();
        $manager = new PackageManager();
        $manager->fetchPackages();
        $this->active = $manager->getActive();
        $this->loadingList = $this->active;
        $this->installed = (new \package\model\service\PackageService(
            new \package\model\repository\PackageRepository()
        ))->getInstalled();
    }
    public function load(): void
    {
        $this->configureExecutor();
        $runtimes = [];
        foreach ($this->loadingList as $origin) {
            $state = \runtime\Runtime::create(LegacyRuntimeAdapter::installedPackage($origin));
            $state->setEventDispatcher($this->eventDispatcher);
            $runtimes[] = $state;
        }
        (new RuntimeOrchestrator($this->executor, $runtimes, $this->installed))->run();
        $this->syncExecutor();
        foreach ($this->active as $name => $origin) $this->loaded[$name] = $origin;
        $this->loadingList = [];
        $this->eventDispatcher->removeListeners('context.get');
    }
    public function extractPackageObject(Runtime $runtime): array
    {
        $this->eventDispatcher->removeListeners('context.get');
        $this->eventDispatcher->addListener('context.get', new RespondContextEvent(
            new Context(PACKAGES . str_replace(' ', '', $runtime->name), $runtime->name, (string) $runtime->getId())
        ));
        $object = ObjectHandler::createObjectFromFile(PACKAGES . str_replace(' ', '', $runtime->name) . '/main.php');
        if (!$object instanceof RuntimeAPI) throw new \RuntimeException('Missing legacy RuntimeAPI: ' . $runtime->name);
        return [$object];
    }
}
