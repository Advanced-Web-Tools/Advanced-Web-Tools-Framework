<?php
namespace runtime;

use runtime\enums\ERuntimeFlags;
use runtime\interface\IRuntimeFlagHandler;

final class RuntimeFlagHandler implements IRuntimeFlagHandler
{
    /** Validate and deduplicate native runtime flags. */
    public function normalize(array $flags): array
    {
        $normalized = [];
        foreach ($flags as $flag) {
            if (!$flag instanceof ERuntimeFlags) {
                throw new \InvalidArgumentException('Runtime flags must be ERuntimeFlags cases.');
            }
            $normalized[$flag->name] = $flag;
        }
        return array_values($normalized);
    }
}
