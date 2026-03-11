<?php

namespace vfs\cache;

use vfs\cache\enums\ECacheValidation;
use vfs\transient\enums\ETransientType;
use vfs\transient\TransientStorage;
use vfs\transient\TransientStorageEntry;

class CachePool
{
    public string $pool;

    public array $watched = [];

    public ECacheValidation $cacheValidation;

    private TransientStorage $transientStorage;

    public function __construct(string $pool)
    {
        $this->pool = $pool;

        $this->transientStorage = new TransientStorage();
        $this->transientStorage
            ->setPool("cache")
            ->setSubPool($pool);

        $this->cacheValidation = ECacheValidation::NONE;

        $config = $this->transientStorage->getFile("config.json");

        if ($config !== null) {

            $config->loadContent();
            $data = json_decode($config->content, true);

            $this->cacheValidation = ECacheValidation::from($data["validation"]);
            $this->watched = $data["watched"] ?? [];
        }
    }

    public function createConfig(ECacheValidation $validation, array $watched): self
    {
        $this->cacheValidation = $validation;

        foreach ($watched as $file) {
            $scan = $this->scanDirectory($file);
            foreach ($scan as $entry) {
                $watched[] = $file . DIRECTORY_SEPARATOR . $entry;
            }
        }

        $this->watched = $watched;

        $this->transientStorage->createFile(
            "config",
            ETransientType::JSON,
            json_encode([
                "validation" => $validation->value,
                "watched" => $watched
            ])
        );

        return $this;
    }

    private function cacheFileName(string $name): string
    {
        return hash("sha256", $name);
    }

    public function setCache(string $name, array $data): CacheEntry
    {
        $watchedData = [];

        if ($this->cacheValidation === ECacheValidation::MODIFIED) {

            foreach ($this->watched as $file) {
                if (file_exists($file)) {
                    $watchedData[$file] = filemtime($file);
                }
            }

        } elseif ($this->cacheValidation === ECacheValidation::HASH) {

            foreach ($this->watched as $file) {
                if (file_exists($file)) {
                    $watchedData[$file] = hash_file("sha256", $file);
                }
            }

        }

        $payload = [
            "time" => time(),
            "watched" => $watchedData,
            "data" => $data
        ];

        $file = $this->transientStorage->createFile(
            $this->cacheFileName($name),
            ETransientType::PHP,
            "<?php return " . var_export($payload, true) . ";"
        );

        return new CacheEntry($name, $data, $file);
    }

    public function getCache(string $name): bool|array
    {
        $entry = $this->transientStorage->getFile(
            $this->cacheFileName($name) . ".php"
        );

        if ($entry === null) {
            return false;
        }

        $data = require $entry->path;

        if (!$this->validate($entry, $data)) {

            $this->transientStorage->deleteFile($entry);
            return false;
        }

        return $data["data"];
    }

    public function deleteCache(string $name): bool
    {
        $entry = $this->transientStorage->getFile(
            $this->cacheFileName($name) . ".php"
        );

        if ($entry === null) {
            return false;
        }

        return $this->transientStorage->deleteFile($entry);
    }

    private function validate(TransientStorageEntry $entry, array $data): bool
    {
        return match ($this->cacheValidation) {

            ECacheValidation::NONE => true,

            ECacheValidation::EXPIRE =>
                time() - $data["time"] < 3600,

            ECacheValidation::EXPIRE_LONGER =>
                time() - $data["time"] < 86400,

            ECacheValidation::MODIFIED =>
            $this->validateModified($data),

            ECacheValidation::HASH =>
            $this->validateHash($data),
        };
    }

    private function validateModified(array $cacheData): bool
    {
        if (!isset($cacheData['watched'])) {
            return false;
        }

        foreach ($cacheData['watched'] as $file => $storedMTime) {

            if (!file_exists($file)) {
                return false;
            }

            if (filemtime($file) > $storedMTime) {
                return false;
            }


        }

        return true;
    }

    private function validateHash(array $cacheData): bool
    {
        if (!isset($cacheData['watched'])) {
            return false;
        }

        foreach ($cacheData['watched'] as $file => $storedHash) {

            if (!file_exists($file)) {
                return false;
            }

            if (hash_file("sha256", $file) !== $storedHash) {
                return false;
            }
        }

        return true;
    }

    private function scanDirectory(string $directory): array
    {
        return array_diff(scandir($directory), ['.', '..']);
    }
}