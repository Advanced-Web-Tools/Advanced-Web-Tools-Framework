<?php

namespace package\facade;

use package\manifest\exceptions\ManifestReaderException;
use package\manifest\reader\interfaces\IManifestReader;
use package\manifest\reader\ManifestReader;
use package\manifest\service\interface\IManifestService;
use package\manifest\service\ManifestService;
use package\model\Package;

class PackageManifestFacade
{

    private IManifestService $service;
    private IManifestReader $reader;

    /**
     * @throws ManifestReaderException
     */
    public function __construct(string $package)
    {
        $this->reader = new ManifestReader($package);
        $this->service = new ManifestService($this->reader);
    }

    public function getPackage(): Package
    {
        return $this->service->buildPackage();
    }

    public function getReader(): IManifestReader
    {
        return $this->reader;
    }

    public function getService(): IManifestService
    {
        return $this->service;
    }
}