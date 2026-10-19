<?php

namespace installer\profiles;

/** Resolve the CMS distribution independently of the package manifest format. */
final class CmsReleaseRepository
{
    public function releaseUrl(string $repositoryUrl, string $frameworkVersion): string
    {
        $curl = curl_init($repositoryUrl);
        $json = '';
        try {
            curl_setopt_array($curl, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'AWT-Installer/' . $frameworkVersion,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$json): int {
                    if (strlen($json) + strlen($chunk) > 1048576) return 0;
                    $json .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (!curl_exec($curl)) throw new \RuntimeException('Could not read the CMS release repository. Try again later.');
        } finally {
            curl_close($curl);
        }
        try {
            $repository = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \RuntimeException('The CMS release repository contains invalid JSON.', 0, $error);
        }
        if (!is_array($repository)) throw new \RuntimeException('The CMS release repository must contain an object.');
        return $this->selectRelease($repository, $frameworkVersion);
    }

    /** Bounds are inclusive. Prefer a compatible latest entry, then the highest compatible version. */
    public function selectRelease(array $repository, string $frameworkVersion): string
    {
        if (!isset($repository['versions']) || !is_array($repository['versions']) || !array_is_list($repository['versions'])) {
            throw new \RuntimeException('The CMS release repository must contain a versions list.');
        }
        $compatible = [];
        foreach ($repository['versions'] as $release) {
            if (!is_array($release)
                || !$this->isVersion($release['version'] ?? null)
                || !$this->isVersion($release['minimum_awt_version'] ?? null)
                || (($release['maximum_awt_version'] ?? null) !== null && !$this->isVersion($release['maximum_awt_version']))
                || !is_string($release['release_url'] ?? null)
                || !filter_var($release['release_url'], FILTER_VALIDATE_URL)
                || !in_array(strtolower(parse_url($release['release_url'], PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)
                || (isset($release['latest']) && !is_bool($release['latest']))) {
                throw new \RuntimeException('The CMS release repository contains an invalid version entry.');
            }
            if (version_compare($frameworkVersion, $release['minimum_awt_version'], '<')) continue;
            if (($release['maximum_awt_version'] ?? null) !== null && version_compare($frameworkVersion, $release['maximum_awt_version'], '>')) continue;
            $compatible[] = $release;
        }
        if ($compatible === []) throw new \RuntimeException('No CMS release is compatible with AWT ' . $frameworkVersion . '.');
        usort($compatible, static function (array $a, array $b): int {
            $latest = (int) ($b['latest'] ?? false) <=> (int) ($a['latest'] ?? false);
            return $latest ?: version_compare($b['version'], $a['version']);
        });
        return $compatible[0]['release_url'];
    }

    private function isVersion(mixed $version): bool
    {
        return is_string($version) && preg_match('/^\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/D', $version) === 1;
    }
}
