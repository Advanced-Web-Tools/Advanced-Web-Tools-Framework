<?php

namespace vfs\cache;

use vfs\cache\enums\ECacheValidation;
use vfs\transient\enums\ETransientType;
use vfs\transient\interfaces\ITransientStorage;
use vfs\transient\TransientStorage;

class CachePool
{
    public string $pool;
    public array $watched = [];
    public ECacheValidation $cacheValidation = ECacheValidation::NONE;
    private ITransientStorage $transientStorage;

    public function __construct(string $pool)
    {
        $this->pool = $pool;
        $this->transientStorage = (new TransientStorage())->setPool('cache')->setSubPool($pool);
        $config = $this->transientStorage->getFile('config.json');
        if ($config !== null) {
            $config->loadContent();
            $data = is_string($config->content) ? json_decode($config->content, true) : null;
            if (is_array($data) && is_string($data['validation'] ?? null)) {
                $this->cacheValidation = ECacheValidation::tryFrom($data['validation']) ?? ECacheValidation::NONE;
                $this->watched = array_values(array_filter(is_array($data['watched'] ?? null) ? $data['watched'] : [], 'is_string'));
            }
        }
    }

    public function createConfig(ECacheValidation $validation, array $watched): self
    {
        foreach ($watched as $path) {
            if (!is_string($path) || $path === '') throw new \InvalidArgumentException('Watched paths must be nonempty strings.');
        }
        $this->cacheValidation = $validation;
        $this->watched = array_values(array_unique($watched));
        $this->transientStorage->createFile('config', ETransientType::JSON, json_encode([
            'validation' => $validation->value, 'watched' => $this->watched,
        ], JSON_THROW_ON_ERROR));
        return $this;
    }

    private function cacheFileName(string $name): string { return hash('sha256', $name); }

    public function setCache(string $name, array $data): CacheEntry
    {
        $payload = ['version' => 2, 'time' => time(), 'validation' => $this->cacheValidation->value,
            'roots' => $this->watched, 'watched' => $this->snapshot($this->watched, $this->cacheValidation), 'data' => $data];
        $file = $this->transientStorage->createFile($this->cacheFileName($name), ETransientType::PHP, $payload);
        return new CacheEntry($name, $data, $file);
    }

    public function getCache(string $name): bool|array
    {
        $entry = $this->transientStorage->getFile($this->cacheFileName($name) . '.php');
        if ($entry === null) return false;
        try {
            $data = @include $entry->getPath();
            if (is_array($data) && $this->validate($data)) return $data['data'];
        } catch (\Throwable $e) {
            // A corrupt or obsolete cache is a miss; application data is rebuilt.
        }
        $this->transientStorage->deleteFile($entry);
        return false;
    }

    public function deleteCache(string $name): bool
    {
        $entry = $this->transientStorage->getFile($this->cacheFileName($name) . '.php');
        return $entry !== null && $this->transientStorage->deleteFile($entry);
    }

    private function validate(array $data): bool
    {
        if (($data['version'] ?? null) !== 2 || !is_array($data['data'] ?? null)
            || !is_int($data['time'] ?? null) || !is_string($data['validation'] ?? null)) return false;
        $mode = ECacheValidation::tryFrom($data['validation']);
        if ($mode === null) return false;
        return match ($mode) {
            ECacheValidation::NONE => true,
            ECacheValidation::EXPIRE => time() - $data['time'] < 3600,
            ECacheValidation::EXPIRE_LONGER => time() - $data['time'] < 86400,
            ECacheValidation::MODIFIED, ECacheValidation::HASH =>
                is_array($data['roots'] ?? null) && is_array($data['watched'] ?? null)
                && $this->snapshot($data['roots'], $mode) === $data['watched'],
        };
    }

    private function snapshot(array $paths, ECacheValidation $mode): array
    {
        if ($mode !== ECacheValidation::MODIFIED && $mode !== ECacheValidation::HASH) return [];
        $result = [];
        foreach ($paths as $path) $this->watch($path, $mode, $result, true);
        ksort($result);
        return $result;
    }

    private function watch(string $path, ECacheValidation $mode, array &$result, bool $root = false): void
    {
        clearstatcache(true, $path);
        if (is_link($path)) {
            $result[$path] = ['link' => readlink($path), 'target' => realpath($path)];
            // Do not recurse through directory links: package trees may contain cycles.
            if (!is_file($path) && !$root) return;
        }
        if (is_dir($path)) {
            $names = @scandir($path);
            if ($names === false) throw new \RuntimeException("Cannot scan watched directory: {$path}");
            $names = array_values(array_diff($names, ['.', '..']));
            $result[$path] = ($result[$path] ?? []) + ['directory' => $names];
            foreach ($names as $name) $this->watch(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name, $mode, $result);
        } elseif (is_file($path)) {
            $stat = @stat($path);
            if ($stat === false) throw new \RuntimeException("Cannot stat watched file: {$path}");
            $signature = ['mtime' => $stat['mtime'], 'ctime' => $stat['ctime'], 'size' => $stat['size']];
            if ($mode === ECacheValidation::HASH) {
                $hash = @hash_file('sha256', $path);
                if ($hash === false) throw new \RuntimeException("Cannot hash watched file: {$path}");
                $signature['hash'] = $hash;
            }
            $result[$path] = ($result[$path] ?? []) + $signature;
        } else $result[$path] = ['missing' => true];
    }
}
