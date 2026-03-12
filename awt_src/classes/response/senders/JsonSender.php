<?php

namespace response\senders;

class JsonSender extends AbstractSender
{
    public function supportedMimes(): array
    {
        return ['application/json'];
    }

    /**
     * $payload may be:
     *  - string  → treated as a file path to a .json file
     *  - array   → encoded directly to JSON
     */
    public function send(mixed $payload, int $status, array $headers): void
    {
        $this->emitStatus($status);
        $this->emitContentType('application/json');
        $this->emitHeaders($headers);

        if (is_string($payload)) {
            $raw = file_get_contents($payload);
            json_decode($raw);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("File '$payload' is not valid JSON.");
            }
            echo $raw;
        } else {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
}
