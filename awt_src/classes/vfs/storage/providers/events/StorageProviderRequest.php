<?php

namespace vfs\storage\providers\events;

use event\interfaces\IEvent;
use event\interfaces\IEventListener;

class StorageProviderRequest implements IEventListener
{

    /**
     * @inheritDoc
     */
    public function handle(IEvent $event): array
    {
        // TODO: Implement handle() method.
        return [];
    }
}