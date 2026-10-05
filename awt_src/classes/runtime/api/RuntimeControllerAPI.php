<?php
namespace runtime\api;
use context\Context;
use controller\Controller;
use object\ObjectFactory;
use runtime\enums\ERuntimeFlags;
use runtime\interface\api\IRuntimeControllerCapabilities;
abstract class RuntimeControllerAPI extends RuntimeAPI implements IRuntimeControllerCapabilities
{
    public array $controllers = [];
    public function environmentSetup(): void
    {
        parent::environmentSetup();
        $this->setRuntimeFlag(ERuntimeFlags::Controller);
        $this->setRuntimeFlag(ERuntimeFlags::CreatePassable);
    }
    protected function addController(ObjectFactory|Controller $controller, string $name = ''): void
    {
        if ($controller instanceof ObjectFactory && $controller->type === null) {
            throw new \InvalidArgumentException('Controller factories must declare their expected type.');
        }
        $this->controllers[$controller instanceof Controller ? $controller->controllerName : $name] = $controller;
    }
    public function getController(string $name): ObjectFactory|Controller
    {
        $controller = $this->controllers[$name] ?? throw new \OutOfBoundsException("Unknown controller: {$name}");
        $context = new Context($this->rootPath, $this->name, (string) $this->id);
        if ($controller instanceof ObjectFactory) {
            $controller->addProperty('packageName', $this->name);
            $controller->addMethodCall('setContext')->addMethodArgs('setContext', [$context]);
        } else {
            $controller->packageName = $this->name;
            $controller->setContext($context);
        }
        return $controller;
    }
}
