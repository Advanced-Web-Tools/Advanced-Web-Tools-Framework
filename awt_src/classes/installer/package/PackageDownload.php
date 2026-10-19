<?php

namespace installer\package;

use RuntimeException;

/** Shared HTTP(S) archive download for CLI packages and recursive dependencies. */
class PackageDownload
{
    public function download(string $url, string $destination): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)
            || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            throw new RuntimeException('Package download requires an HTTP or HTTPS URL.');
        }
        $file = fopen($destination, 'wb');
        if ($file === false) throw new RuntimeException('Failed to create temporary package archive.');
        $curl = curl_init($url);
        try {
            curl_setopt_array($curl, [
                CURLOPT_FILE => $file,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ]);
            if (!curl_exec($curl)) {
                throw new RuntimeException('Failed to download package: ' . curl_error($curl));
            }
        } finally {
            curl_close($curl);
            fclose($file);
        }
    }
}
