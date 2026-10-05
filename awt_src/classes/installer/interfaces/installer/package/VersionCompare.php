<?php

namespace installer\package;

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
        return version_compare(AWT_VERSION, $min, '>=') && (!$max || version_compare(AWT_VERSION, $max, '<='));
    }
}