<?php

namespace response\senders;

class BinarySender extends AbstractSender
{
    public function supportedMimes(): array
    {
        return ['application/octet-stream'];
    }

    /**
     * $payload must be a file path.
     * Forces a file-download dialog in the browser.
     */
    public function send(mixed $payload, int $status, array $headers): void
    {
        $path = (string) $payload;
        $this->requireReadable($path);

        $size     = $this->fileSize($path);
        $filename = basename($path);

        $this->emitStatus($status);
        $this->emitContentType('application/octet-stream');
        header("Content-Length: $size");

        if (!isset($headers['Content-Disposition'])) {
            header("Content-Disposition: attachment; filename=\"$filename\"");
        }

        $this->emitHeaders($headers);
        $this->streamFile($path);
    }
}
