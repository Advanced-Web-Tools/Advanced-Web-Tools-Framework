<?php

namespace response\senders;

class PdfSender extends AbstractSender
{
    public function supportedMimes(): array
    {
        return ['application/pdf'];
    }

    /**
     * $payload must be a file path.
     * Pass header 'Content-Disposition: attachment; filename="file.pdf"'
     * via $headers if you want a forced download rather than inline view.
     */
    public function send(mixed $payload, int $status, array $headers): void
    {
        $path = (string) $payload;
        $this->requireReadable($path);

        $size = $this->fileSize($path);

        $this->emitStatus($status);
        $this->emitContentType('application/pdf');
        header("Content-Length: $size");

        // Default to inline viewing unless the caller sets Content-Disposition.
        if (!isset($headers['Content-Disposition'])) {
            $filename = basename($path);
            header("Content-Disposition: inline; filename=\"$filename\"");
        }

        $this->emitHeaders($headers);
        $this->streamFile($path);
    }
}
