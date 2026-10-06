<?php
namespace runtime;

use event\EventDispatcher;
use package\model\InstalledPackage;
use runtime\enums\ERuntimeStatus;
use runtime\interface\IRuntime;

/** Execution state for one installed package, independent of its package API. */
class Runtime implements IRuntime
{
    public string $rootPath = '';
    public array $waitFor = [];
    public array $flags = [];
    public array $shared = [];
    public array $passables = [];
    public ?EventDispatcher $eventDispatcher = null;
    public ERuntimeStatus $status = ERuntimeStatus::NOT_STARTED;

    public function __construct(private readonly ?InstalledPackage $package = null)
    {
        if ($package !== null) {
            $this->rootPath = PACKAGES . str_replace(' ', '', $package->getName()) . DIRECTORY_SEPARATOR;
        }
    }

    public static function create(?InstalledPackage $package = null): self { return new self($package); }
    public function setEventDispatcher(EventDispatcher $dispatcher): void { $this->eventDispatcher = $dispatcher; }
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher ?? throw new \LogicException('Runtime event dispatcher has not been initialized.');
    }
    public function setRuntimeStatus(ERuntimeStatus $status): void { $this->status = $status; }
    public function getRuntimeStatus(): ERuntimeStatus { return $this->status; }
    public function getPackage(): InstalledPackage
    {
        return $this->package ?? throw new \LogicException('Runtime has no installed package.');
    }
    public function getRuntimeId(): int { return $this->getPackage()->getId(); }
    public function getRuntimeName(): string { return $this->getPackage()->getName(); }
    public function getRootPath(): string { return $this->rootPath; }
    public function setFlags(array $flags): void { $this->flags = $flags; }
    public function getFlags(): array { return $this->flags; }
    public function setWaitFor(array $packages): void { $this->waitFor = $packages; }
    public function getWaitFor(): array { return $this->waitFor; }
    public function setPassable(array $passables): void { $this->passables = $passables; }
    public function getPassable(string $name): array { return $this->passables[$name] ?? []; }
    public function setSharedRegistry(array $shared): void { $this->shared = $shared; }
    public function getSharedRegistry(): array { return $this->shared; }
}
