<?php

namespace response\senders;

class CssSender extends AbstractSender
{
    public function supportedMimes(): array
    {
        return ['text/css'];
    }

    public function send(mixed $payload, int $status, array $headers): void
    {
        $this->emitStatus($status);
        $this->emitContentType('text/css');
        $this->emitHeaders($headers);

        if (is_string($payload) && file_exists($payload)) {
            $this->streamFile($payload);
        } else {
            echo (string) $payload;
        }
    }
}
