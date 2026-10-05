<?php

namespace runtime;

use package\model\InstalledPackage;
use runtime\interface\IRuntime;
use runtime\interface\IRuntimeCreator;

readonly class RuntimeCreator implements IRuntimeCreator
{
    public function __construct(private IRuntime $runtimeBase)
    {}

    public function create(InstalledPackage $packages): IRuntime
    {
        return $this->runtimeBase::create($packages);
    }
}