<?php

namespace response\interface;

interface SenderInterface
{
    /**
     * Emit the response to the client.
     *
     * @param mixed  $payload  Raw data, file path, or structured array.
     * @param int    $status   HTTP status code.
     * @param array  $headers  Extra headers to emit before sending body.
     */
    public function send(mixed $payload, int $status, array $headers): void;

    /**
     * MIME types this sender is responsible for.
     *
     * @return string[]
     */
    public function supportedMimes(): array;
}
