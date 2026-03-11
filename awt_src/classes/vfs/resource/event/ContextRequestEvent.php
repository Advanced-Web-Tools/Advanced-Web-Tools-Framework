<?php

namespace vfs\resource\event;

use event\interfaces\IEvent;

class ContextRequestEvent implements IEvent
{
    public string $context;

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