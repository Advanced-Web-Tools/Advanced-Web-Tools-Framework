<?php

namespace installer\package;

use installer\framework\FrameworkVersion;
use installer\interfaces\package\IVersionCompare;

class VersionCompare implements IVersionCompare
{

    /**
     * @inheritDoc
     */
    public function compare(string $old, string $new): int
    {
        return version_compare($old, $new);
    }

    /**
     * @inheritDoc
     */
    public function compareCompatibility(string $min, ?string $max = null): bool
    {
        $version = FrameworkVersion::current();
        return version_compare($version, $min, '>=') && (!$max || version_compare($version, $max, '<='));
    }
}
