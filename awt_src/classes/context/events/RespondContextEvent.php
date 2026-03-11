<?php

namespace context\events;

use context\Context;
use event\interfaces\IEvent;
use event\interfaces\IEventListener;

final class RespondContextEvent implements IEventListener
{
    public Context $context;
    public function __construct(Context $context)
    {
        $this->context = $context;
    }
    public function handle(IEvent $event): array
    {
        $context = $event->bundle()['context'];
        $context->setContext($this->context);
        return ["context" => $context, "event" => $event, "type" => "respond"];
    }
}