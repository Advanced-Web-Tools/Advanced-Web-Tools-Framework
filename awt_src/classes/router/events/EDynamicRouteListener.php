<?php

namespace router\events;

use event\interfaces\IEvent;
use event\interfaces\IEventListener;
use router\manager\RouterManager;
use router\interface\IRouterManager;

final class EDynamicRouteListener implements IEventListener
{

    private IRouterManager $routerManager;

    public function __construct(?IRouterManager $routerManager = null)
    {
        $this->addManager($routerManager);
    }

    public function addManager(?IRouterManager $routerManager): void
    {
        if($routerManager === null) {
            $this->routerManager = new RouterManager();
        } else {
            $this->routerManager = $routerManager;
        }
    }
    public function handle(IEvent $event): array
    {
        $this->routerManager->loadRouters($event->bundle());
        return [];
    }
}