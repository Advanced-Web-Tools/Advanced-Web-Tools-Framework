<?php

namespace installer\interfaces\package;

interface IVersionCompare
{
    /**
     * Compares two versions.
     * @param string $old
     * @param string $new
     * @return int
     */
    public function compare(string $old, string $new): int;

    /**
     * Checks if the current version is compatible with the given version range.
     * @param string $min
     * @param string|null $max
     * @return bool
     */
    public function compareCompatibility(string $min, ?string $max = null): bool;
}