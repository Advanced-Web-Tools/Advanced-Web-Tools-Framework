<?php
namespace runtime\api;
use runtime\enums\ERuntimeFlags;
use runtime\interface\api\IRuntimeLinkerCapabilities;
abstract class RuntimeLinkerAPI extends RuntimeAPI implements IRuntimeLinkerCapabilities
{
    public array $links = [];
    public function environmentSetup(): void
    {
        parent::environmentSetup();
        $this->setRuntimeFlag(ERuntimeFlags::RuntimeLinker);
        $this->setRuntimeFlag(ERuntimeFlags::CreatePassable);
    }
    public function getLinks(): array { return $this->links; }
    public function createLink(string $name, string $path): void
    {
        $this->links[$name] = $this->rootPath . '/' . ltrim($path, '/');
    }
}
