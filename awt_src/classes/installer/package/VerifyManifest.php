<?php

namespace installer\package;

use installer\interfaces\package\IVerifyManifest;
use RuntimeException;

class VerifyManifest implements IVerifyManifest
{
    private array $manifest;
    private array $verificationList = [];


    public function __construct(array $manifest)
    {
        $this->manifest = $manifest;
    }


    /**
     * @inheritDoc
     */
    public function addKey(string $key): IVerifyManifest
    {
        $this->verificationList[$key] = "*";
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyWithType(string $key, string $type): IVerifyManifest
    {
        $this->verificationList[$key] = $type;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function verify(): bool
    {
        foreach ($this->verificationList as $key => $type) {
            if (!array_key_exists($key, $this->manifest)) {
                throw new RuntimeException("Manifest key not found: {$key}");
            }
            if ($type === '*') {
                continue;
            }
            $normalized = match ($type) {
                'bool'  => 'boolean',
                'int'   => 'integer',
                'float' => 'double',
                default => $type,
            };
            if (gettype($this->manifest[$key]) !== $normalized) {
                throw new RuntimeException("Manifest key type mismatch: {$key} expected {$type}, got " . gettype($this->manifest[$key]));
            }
        }
        return true;
    }
}