<?php

namespace uninstaller\interfaces;

interface IPackageCleanup
{
    /** @return string[] Failure messages; an empty array means success. */
    public function clean(int $id, string $name): array;
}
