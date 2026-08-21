<?php

namespace installer\interfaces\package;

interface IVerifyStructure
{
    /**
     * Adds a file path to verify, relative to the package root (e.g. /assets/css/style.css).
     * @param string $file
     * @return self
     */
    public function addFile(string $file): self;

    /**
     * Adds a directory path to verify, relative to the package root (e.g. /assets/css).
     * @param string $dir
     * @return self
     */
    public function addDirectory(string $dir): self;

    /**
     * Returns true if all registered files exist and are readable, and all registered directories exist.
     * @return bool
     */
    public function verify(): bool;
}