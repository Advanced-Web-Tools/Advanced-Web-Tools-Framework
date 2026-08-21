<?php

namespace installer\package;

use installer\interfaces\package\IVerifyStructure;
use RuntimeException;

class VerifyStructure implements IVerifyStructure
{

    private array $required;
    private array $scanned;

    public function __construct(
        private readonly string $root
    ){
        $this->scanned = [
            "directories" => [],
            "files" => []
        ];
    }

    /**
     * @inheritDoc
     */
    public function addFile(string $file): IVerifyStructure
    {
        $this->required["files"][] = $file;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addDirectory(string $dir): IVerifyStructure
    {
        $this->required["directories"][] = $dir;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function verify(): bool
    {
        $this->generateStructure();

        foreach ($this->required["files"] ?? [] as $file) {
            if (!in_array($file, $this->scanned["files"], true)
                || !is_readable($this->root . DIRECTORY_SEPARATOR . $file)) {
                return false;
            }
        }

        foreach ($this->required["directories"] ?? [] as $dir) {
            if (!in_array($dir, $this->scanned["directories"], true)) {
                return false;
            }
        }

        return true;
    }

    //Helper function

    private function generateStructure(): void
    {
        if(!is_dir($this->root)) {
            throw new RuntimeException("Root directory of the package does not exist. $this->root");
        }

        $this->scanDirectory($this->root);

    }


    private function scanDirectory(string $dir): void
    {
        $scan = scandir($dir);
        unset($scan[0], $scan[1]);

        foreach($scan as $entry) {
            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            $relativePath = substr($fullPath, strlen($this->root) + 1);

            if(is_dir($fullPath)) {
                $this->scanned["directories"][] = $relativePath;
                $this->scanDirectory($fullPath);
            } else {
                $this->scanned["files"][] = $relativePath;
            }
        }
    }

}