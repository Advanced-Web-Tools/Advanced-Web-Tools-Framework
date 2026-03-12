<?php

namespace response\senders;

class AudioSender extends AbstractSender
{
    private const MIMES = [
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
        'audio/webm',
        'audio/aac',
        'audio/flac',
        'audio/x-m4a',
    ];

    public function supportedMimes(): array
    {
        return self::MIMES;
    }

    /**
     * $payload must be a file path.
     * Handles HTTP Range requests for audio seeking.
     */
    public function send(mixed $payload, int $status, array $headers): void
    {
        $path = (string) $payload;
        $this->requireReadable($path);

        $mime     = $this->mimeFromPath($path);
        $fileSize = $this->fileSize($path);
        $range    = $_SERVER['HTTP_RANGE'] ?? null;

        if ($range) {
            [$start, $end] = $this->parseRange($range, $fileSize);
            $length        = $end - $start + 1;

            http_response_code(206);
            $this->emitContentType($mime);
            header("Content-Length: $length");
            header("Content-Range: bytes $start-$end/$fileSize");
            header('Accept-Ranges: bytes');
            $this->emitHeaders($headers);

            $this->streamRange($path, $start, $length);
        } else {
            $this->emitStatus($status);
            $this->emitContentType($mime);
            header("Content-Length: $fileSize");
            header('Accept-Ranges: bytes');
            $this->emitHeaders($headers);

            $this->streamFile($path);
        }
    }

    private function mimeFromPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'mp3'         => 'audio/mpeg',
            'ogg', 'oga'  => 'audio/ogg',
            'wav'         => 'audio/wav',
            'weba'        => 'audio/webm',
            'aac'         => 'audio/aac',
            'flac'        => 'audio/flac',
            'm4a'         => 'audio/x-m4a',
            default       => throw new \RuntimeException("Unsupported audio extension: '.$ext'."),
        };
    }
}
