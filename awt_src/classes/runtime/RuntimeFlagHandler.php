<?php
namespace runtime;

use runtime\enums\ERuntimeFlags;
use runtime\interface\IRuntimeFlagHandler;

final class RuntimeFlagHandler implements IRuntimeFlagHandler
{
    /** Legacy and native flags have the same meanings, with one renamed case. */
    public function normalize(array $flags): array
    {
        $normalized = [];
        foreach ($flags as $flag) {
            if (!$flag instanceof \UnitEnum) {
                throw new \InvalidArgumentException('Runtime flags must be enum cases.');
            }
            $name = $flag->name === 'CreatePassableObject' ? 'CreatePassable' : $flag->name;
            foreach (ERuntimeFlags::cases() as $candidate) {
                if ($candidate->name === $name) {
                    $normalized[$name] = $candidate;
                    continue 2;
                }
            }
            throw new \InvalidArgumentException('Unknown runtime flag: ' . $name);
        }
        return array_values($normalized);
    }
}
