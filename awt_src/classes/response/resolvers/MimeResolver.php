<?php

namespace response\resolvers;

class MimeResolver
{
    /** Map of file extension → MIME type. */
    private const EXT_MAP = [
        // Text / structured
        'json'        => 'application/json',
        'html', 'htm' => 'text/html',
        'css'         => 'text/css',
        'js', 'mjs'   => 'application/javascript',
        'xml'         => 'application/xml',
        'txt'         => 'text/plain',
        'csv'         => 'text/csv',
        // PDF
        'pdf'         => 'application/pdf',
        // Images
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        'webp'        => 'image/webp',
        'svg'         => 'image/svg+xml',
        'ico'         => 'image/x-icon',
        'bmp'         => 'image/bmp',
        'tiff', 'tif' => 'image/tiff',
        'avif'        => 'image/avif',
        // Audio
        'mp3'         => 'audio/mpeg',
        'wav'         => 'audio/wav',
        'ogg', 'oga'  => 'audio/ogg',
        'aac'         => 'audio/aac',
        'flac'        => 'audio/flac',
        'm4a'         => 'audio/x-m4a',
        'weba'        => 'audio/webm',
        // Video
        'mp4'         => 'video/mp4',
        'webm'        => 'video/webm',
        'ogv'         => 'video/ogg',
        'mov'         => 'video/quicktime',
        'avi'         => 'video/x-msvideo',
        'mkv'         => 'video/x-matroska',
        '3gp'         => 'video/3gpp',
    ];

    /**
     * Resolve MIME from a file path's extension.
     * Returns null when extension is unknown.
     */
    public function fromPath(string $path): ?string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return self::EXT_MAP[$ext] ?? null;
    }

    /**
     * Resolve the best MIME from an HTTP Accept header string.
     *
     * Picks the first accepted type that we recognise, respecting q-values.
     * Falls back to null when nothing matches.
     */
    public function fromAccept(string $accept): ?string
    {
        $known = array_unique(array_values(self::EXT_MAP));
        $parts = array_map('trim', explode(',', $accept));

        // Build [ mime => q ] with q-value sorting.
        $weighted = [];
        foreach ($parts as $part) {
            [$type, $params] = array_pad(explode(';', $part, 2), 2, '');
            $type = strtolower(trim($type));
            $q    = 1.0;
            if (preg_match('/q=([\d.]+)/', (string) $params, $m)) {
                $q = (float) $m[1];
            }
            $weighted[$type] = $q;
        }
        arsort($weighted);

        foreach (array_keys($weighted) as $accepted) {
            // Exact match.
            if (in_array($accepted, $known, true)) {
                return $accepted;
            }
            // Wildcard: e.g. "text/*" matches "text/html".
            if (str_ends_with($accepted, '/*')) {
                $prefix = substr($accepted, 0, -1); // "text/"
                foreach ($known as $mime) {
                    if (str_starts_with($mime, $prefix)) {
                        return $mime;
                    }
                }
            }
        }

        return null;
    }
}
