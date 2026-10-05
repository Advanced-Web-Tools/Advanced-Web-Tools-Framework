<?php

namespace response\senders;

class HtmlSender extends AbstractSender
{
    public function supportedMimes(): array
    {
        return ['text/html', 'application/xhtml+xml'];
    }

    /**
     * $payload may be:
     *  - string path to an .html file
     *  - string raw HTML markup (detected by absence of a readable file at that path)
     */
    public function send(mixed $payload, int $status, array $headers): void
    {
        $this->emitStatus($status);
        $this->emitContentType('text/html; charset=UTF-8');
        $this->emitHeaders($headers);

        if (is_string($payload) && file_exists($payload)) {
            $this->streamFile($payload);
        } else {
            echo (string) $payload;
        }
    }
}
