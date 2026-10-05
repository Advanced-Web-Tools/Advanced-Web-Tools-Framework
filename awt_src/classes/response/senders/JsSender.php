<?php

namespace response\senders;

class JsSender extends AbstractSender
{
    public function supportedMimes(): array
    {
        return ['application/javascript', 'text/javascript'];
    }

    public function send(mixed $payload, int $status, array $headers): void
    {
        $this->emitStatus($status);
        $this->emitContentType('application/javascript');
        $this->emitHeaders($headers);

        if (is_string($payload) && file_exists($payload)) {
            $this->streamFile($payload);
        } else {
            echo (string) $payload;
        }
    }
}
