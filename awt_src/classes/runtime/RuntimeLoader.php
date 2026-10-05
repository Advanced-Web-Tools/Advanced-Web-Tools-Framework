<?php
namespace runtime;
use package\model\service\interface\IPackageService;
use runtime\interface\IRuntimeCreator;
use runtime\interface\IRuntimeLoader;
class RuntimeLoader implements IRuntimeLoader
{
    public array $runtimes = [];
    public function __construct(private IPackageService $packageService, private IRuntimeCreator $runtimeCreator) {}
    public function load(): void
    {
        $this->runtimes = [];
        foreach ($this->packageService->getActive() as $package) {
            if ($package->getType() !== 0) $this->runtimes[] = $this->runtimeCreator->create($package);
        }
    }
    public function getLoaded(): array { return $this->runtimes; }
}
