<?php

namespace response\senders;

class VideoSender extends AbstractSender
{
    private const MIMES = [
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/3gpp',
    ];

    public function supportedMimes(): array
    {
        return self::MIMES;
    }

    /**
     * $payload must be a file path.
     * Handles HTTP Range requests for browser-native video seeking.
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
            'mp4'         => 'video/mp4',
            'webm'        => 'video/webm',
            'ogv', 'ogg'  => 'video/ogg',
            'mov'         => 'video/quicktime',
            'avi'         => 'video/x-msvideo',
            'mkv'         => 'video/x-matroska',
            '3gp'         => 'video/3gpp',
            default       => throw new \RuntimeException("Unsupported video extension: '.$ext'."),
        };
    }
}
