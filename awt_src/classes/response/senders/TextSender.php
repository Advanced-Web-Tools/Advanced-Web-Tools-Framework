<?php

namespace response\senders;

class TextSender extends AbstractSender
{
    public function supportedMimes(): array
    {
        return ['text/plain'];
    }

    public function send(mixed $payload, int $status, array $headers): void
    {
        $this->emitStatus($status);
        $this->emitContentType('text/plain; charset=UTF-8');
        $this->emitHeaders($headers);

        if (is_string($payload) && file_exists($payload)) {
            $this->streamFile($payload);
        } else {
            echo (string) $payload;
        }
    }
}
