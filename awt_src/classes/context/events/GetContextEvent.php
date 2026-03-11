<?php

namespace context\events;

use context\Context;
use event\interfaces\IEvent;

final class GetContextEvent implements IEvent
{
    private Context $context;
    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'context.get';
    }

    public function bundle(): array
    {
        return ["context" => $this];
    }
}