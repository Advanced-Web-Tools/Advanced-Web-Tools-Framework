<?php

namespace vfs\resource\event;

use event\interfaces\IEvent;
use event\interfaces\IEventListener;

class ContextSwitchEvent implements IEventListener
{
    public string $contextName;
    public function handle(IEvent $event): array
    {
        $context = $event->bundle()['context'];
        $context->context = $this->contextName;
        return [];
    }
}