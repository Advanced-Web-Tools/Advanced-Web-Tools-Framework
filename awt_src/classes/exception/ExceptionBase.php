<?php

namespace exception;

use context\Context;
use context\events\GetContextEvent;

abstract class ExceptionBase extends \Exception
{
    public Context $context;
    private GetContextEvent $event;

    public function __construct(?\Throwable $previous = null)
    {
        global $eventDispatcher;
        $this->event = new GetContextEvent();
        $this->context = new Context("", "System", "/");
        $this->event->setContext($this->context);
        $eventDispatcher->dispatch($this->event);
        $this->context = $this->event->getContext();

        $message = $this->buildMessage();
        if ($previous) {
            $message .= "\nCaused by: " . $previous->getMessage();
        }

        parent::__construct($message, 0, $previous);
    }

    abstract public function buildMessage(): string;
}