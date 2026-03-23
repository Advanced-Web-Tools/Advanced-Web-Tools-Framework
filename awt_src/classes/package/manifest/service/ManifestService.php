<?php

namespace package\manifest\service;

use package\dependency\Dependency;
use package\manifest\reader\interfaces\IManifestReader;
use package\manifest\service\interface\IManifestService;
use package\model\Package;

class ManifestService implements IManifestService
{
    public function __construct(
        private readonly IManifestReader $reader
    )
    {}


    public function buildPackage(): Package
    {
        $manifest = $this->reader->getManifest();
        $deps = $manifest['dependencies'];

        if( !is_array($deps))
            $deps = [];

        unset($manifest['dependencies']);

        $package = new Package();

        $package->setDependencies($deps);
        $package->fromArray($manifest);

        return $this->buildDependencies($deps, $package);
    }

    private function buildDependencies(array $data, Package $package): Package
    {
        foreach ($data as $dep) {
            $package->dependenciesCollection->add($this->buildDependency($dep));
        }
        return $package;
    }

    public function buildDependency(array $data): Dependency
    {
        return Dependency::__fromArray($data);
    }
}