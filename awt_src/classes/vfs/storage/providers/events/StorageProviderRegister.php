<?php

namespace vfs\storage\providers\events;

use event\interfaces\IEvent;

class StorageProviderRegister implements IEvent
{

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        // TODO: Implement getName() method.
        return "storage.provider.register";
    }

    /**
     * @inheritDoc
     */
    public function bundle(): array
    {
        // TODO: Implement bundle() method.
        return [];
    }
}