<?php
namespace package\manifest\service\interface;

use package\dependency\Dependency;
use package\manifest\reader\interfaces\IManifestReader;
use package\model\Package;

interface IManifestService {
    public function __construct(IManifestReader $reader);
    public function buildPackage(): Package;
    public function buildDependency(array $data): Dependency;
}
