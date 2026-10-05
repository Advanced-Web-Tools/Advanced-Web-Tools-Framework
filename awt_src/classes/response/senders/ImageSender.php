<?php

namespace response\senders;

class ImageSender extends AbstractSender
{
    private const MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
        'image/x-icon',
        'image/bmp',
        'image/tiff',
        'image/avif',
    ];

    public function supportedMimes(): array
    {
        return self::MIMES;
    }

    /**
     * $payload must be a file path.
     * MIME is resolved from the file's extension.
     */
    public function send(mixed $payload, int $status, array $headers): void
    {
        $path = (string) $payload;
        $this->requireReadable($path);

        $mime = $this->mimeFromPath($path);
        $size = $this->fileSize($path);

        $this->emitStatus($status);
        $this->emitContentType($mime);
        header("Content-Length: $size");
        $this->emitHeaders($headers);

        $this->streamFile($path);
    }

    private function mimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'ico'         => 'image/x-icon',
            'bmp'         => 'image/bmp',
            'tiff', 'tif' => 'image/tiff',
            'avif'        => 'image/avif',
            default       => throw new \RuntimeException("Unsupported image extension: '.$ext'."),
        };
    }
}
