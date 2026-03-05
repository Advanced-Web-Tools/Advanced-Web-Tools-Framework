<?php

namespace vfs\resource\event;

use event\interfaces\IEvent;
use event\interfaces\IEventListener;

class ContextSwitchEvent implements IEventListener
{
    public ContextRequestEvent $context;
    public string $contextName;

    /**
     * @inheritDoc
     */
    public function handle(IEvent $event): array
    {
        $this->context = $event->bundle()["context"];
        $this->context->contextName = $this->contextName;

        return [];
    }
}