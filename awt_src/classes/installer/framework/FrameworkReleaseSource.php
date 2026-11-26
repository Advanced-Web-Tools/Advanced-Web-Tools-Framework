<?php

namespace installer\framework;

use RuntimeException;

class FrameworkReleaseSource
{
    private const REPOSITORY = 'Advanced-Web-Tools/Advanced-Web-Tools-Framework';
    private const API = 'https://api.github.com/repos/' . self::REPOSITORY;

    public function latestTag(): string
    {
        $release = $this->json('/releases/latest');
        $tag = $release['tag_name'] ?? '';
        if (!is_string($tag) || !preg_match('/^v?\d+\.\d+\.\d+$/D', $tag)) {
            throw new RuntimeException('GitHub did not return a supported framework release tag.');
        }
        return $tag;
    }

    public function tree(string $tag): array
    {
        try {
            $result = $this->json('/git/trees/' . rawurlencode($tag) . '?recursive=1');
        } catch (RuntimeException $error) {
            if ($error->getCode() !== 404 || str_starts_with($tag, 'v')) throw $error;
            $result = $this->json('/git/trees/v' . rawurlencode($tag) . '?recursive=1');
        }
        if (($result['truncated'] ?? true) !== false || !is_array($result['tree'] ?? null)) {
            throw new RuntimeException("GitHub did not return the complete file tree for {$tag}.");
        }
        return $result['tree'];
    }

    public function file(string $tag, string $path): string
    {
        $path = implode('/', array_map('rawurlencode', explode('/', $path)));
        return $this->get('https://raw.githubusercontent.com/' . self::REPOSITORY . '/' . rawurlencode($tag) . '/' . $path);
    }

    private function json(string $endpoint): array
    {
        $data = json_decode($this->get(self::API . $endpoint), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('GitHub returned an invalid release response.');
        return $data;
    }

    private function get(string $url): string
    {
        $curl = curl_init($url);
        try {
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT => 'AWT Framework Updater',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 60,
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if (!is_string($body) || $status !== 200) {
                $reason = curl_error($curl) ?: "HTTP {$status}";
                throw new RuntimeException("GitHub request failed ({$reason}) for {$url}.", $status);
            }
            return $body;
        } finally {
            curl_close($curl);
        }
    }
}
