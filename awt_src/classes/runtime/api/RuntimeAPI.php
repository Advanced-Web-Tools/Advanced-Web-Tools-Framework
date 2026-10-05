<?php
namespace runtime\api;

use cli\CLIHandler;
use cli\interfaces\CLICommand;
use event\EventDispatcher;
use object\ObjectHandler;
use package\model\InstalledPackage;
use runtime\enums\ERuntimeFlags;
use runtime\interface\api\IRuntimeAPI;
use runtime\interface\api\IRuntimeBaseCapabilities;
use runtime\interface\IRuntime;

abstract class RuntimeAPI implements IRuntimeAPI, IRuntimeBaseCapabilities
{
    protected int $id;
    protected string $name;
    protected string $rootPath;
    protected string $runtimePath;
    protected InstalledPackage $package;
    protected IRuntime $runtime;
    public EventDispatcher $eventDispatcher;
    public CLIHandler $CLIHandler;

    public function setInfo(IRuntime $runtime): void
    {
        $this->runtime = $runtime;
        $this->package = $runtime->getPackage();
        $this->id = $runtime->getRuntimeId();
        $this->name = $runtime->getRuntimeName();
        $this->rootPath = rtrim($runtime->getRootPath(), DIRECTORY_SEPARATOR);
        $this->runtimePath = $this->rootPath;
        $this->eventDispatcher = $runtime->getEventDispatcher();
    }
    public function getRuntime(): IRuntime { return $this->runtime; }
    public function environmentSetup(): void {}
    public function setup(): void {}
    abstract public function main(): void;
    public function setRuntimeFlag(ERuntimeFlags|\packages\runtime\handler\enums\ERuntimeFlags $flag): void { $this->runtime->setFlags([...$this->runtime->getFlags(), $flag]); }
    public function waitForPackage(string $package): void { $this->runtime->setWaitFor([...$this->runtime->getWaitFor(), $package]); }
    public function waitForRuntime(string $package): void { $this->waitForPackage($package); }
    public function setShared(string $name, mixed $share): void
    {
        $shared = $this->runtime->getSharedRegistry();
        $shared[$this->name][$name] = $share;
        $this->runtime->setSharedRegistry($shared);
    }
    public function getShared(string $name, string $shared, ?string $expectedType = null): mixed
    {
        $value = $this->runtime->getSharedRegistry()[$name][$shared] ?? null;
        if ($value !== null && $expectedType !== null && !$value instanceof $expectedType) {
            throw new \RuntimeException('Unexpected shared type ' . get_debug_type($value) . "; expected {$expectedType}.");
        }
        return $value;
    }
    public function getPassable(string $className): ?object
    {
        return $this->runtime->getPassable($this->name)[$className] ?? null;
    }
    public function getLocalObject(string $pathFromRoot): ?object
    {
        return ObjectHandler::createObjectFromFile($this->rootPath . '/' . ltrim($pathFromRoot, '/'));
    }
    public function addCommand(CLICommand $command): void
    {
        if (PHP_SAPI === 'cli') {
            if (!isset($this->CLIHandler)) throw new \LogicException('CLI handler is unavailable.');
            $this->CLIHandler->addCommand($command);
        }
    }
}
