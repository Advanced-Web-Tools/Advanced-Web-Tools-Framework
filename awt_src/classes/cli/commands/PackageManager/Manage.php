<?php

namespace cli\commands\PackageManager;

use package\model\repository\interfaces\IPackageRepository;

readonly class Manage
{
    public function __construct(
        private IPackageRepository $packageRepository,
        private int $packageId
    )
    {}
}