<?php
namespace runtime\api;
use router\Router;
use router\interface\IRouter;
use runtime\enums\ERuntimeFlags;
use runtime\interface\api\IRuntimeRouterCapabilities;
abstract class RuntimeRouterAPI extends RuntimeAPI implements IRuntimeRouterCapabilities, IRouter
{
    public array $routers = [];
    public function environmentSetup(): void
    {
        parent::environmentSetup();
        foreach ([ERuntimeFlags::Router, ERuntimeFlags::CreatePassable, ERuntimeFlags::AccessOtherInstances, ERuntimeFlags::EventDispatcher] as $flag) {
            $this->setRuntimeFlag($flag);
        }
    }
    public function addRouter(Router $router): void
    {
        $router->eventDispatcher = $this->eventDispatcher;
        $this->routers[] = $router;
    }
    public function getRouters(): array { return $this->routers; }
}
