<?php

namespace package\manifest\reader;

use package\manifest\exceptions\ManifestReaderException;
use package\manifest\reader\interfaces\IManifestReader;

class ManifestReader implements IManifestReader
{
    public readonly string $package;
    private readonly string $manifestPath;

    /**
     * @throws ManifestReaderException
     */
    public function __construct(string $package)
    {
        $this->manifestPath = PACKAGES . $package . "/manifest.json";
        if (!file_exists($this->manifestPath)) {
            throw new ManifestReaderException($package, $this->manifestPath);
        }
    }

    public function getManifest(): array
    {
        return json_decode(file_get_contents($this->manifestPath), true);
    }
}