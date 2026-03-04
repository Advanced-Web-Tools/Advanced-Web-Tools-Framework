<?php

namespace vfs\event;

use event\interfaces\IEvent;

class ContextRequestEvent implements IEvent
{

    public ContextRequestEvent $context;
    public string $contextName;
    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return "vfs.context.request";
    }

    /**
     * @inheritDoc
     */
    public function bundle(): array
    {
        return ["context" => $this];
    }
}