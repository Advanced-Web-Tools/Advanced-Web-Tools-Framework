<?php

namespace installer\package;

use installer\interfaces\package\IPackageMover;
use RuntimeException;

class PackageMover implements IPackageMover
{

    private string $source;
    private string $destination;
    private array $scanned;

    public function __construct(string $source, string $destination)
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->scanned = [
            "directories" => [],
            "files" => []
        ];
    }

    /**
     * @inheritDoc
     */
    public function move(): void
    {
        if (!is_dir($this->source)) {
            throw new RuntimeException("Source directory does not exist: {$this->source}");
        }

        if (!is_dir($this->destination)) {
            throw new RuntimeException("Destination directory does not exist: {$this->destination}");
        }

        $this->scanDirectory($this->source);

        foreach ($this->scanned["directories"] as $dir) {
            $destDir = $this->destination . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                throw new RuntimeException("Failed to create directory: {$destDir}");
            }
        }

        foreach ($this->scanned["files"] as $file) {
            $srcFile = $this->source . DIRECTORY_SEPARATOR . $file;
            $destFile = $this->destination . DIRECTORY_SEPARATOR . $file;
            if (!rename($srcFile, $destFile)) {
                throw new RuntimeException("Failed to move file from {$srcFile} to {$destFile}");
            }
        }

        foreach (array_reverse($this->scanned["directories"]) as $dir) {
            rmdir($this->source . DIRECTORY_SEPARATOR . $dir);
        }
        rmdir($this->source);
    }

    /**
     * @inheritDoc
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @inheritDoc
     */
    public function getDestination(): string
    {
        return $this->destination;
    }

    /**
     * @inheritDoc
     */
    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    /**
     * @inheritDoc
     */
    public function setDestination(string $destination): void
    {
        $this->destination = $destination;
    }


    //Helper
    private function scanDirectory(string $dir): void
    {
        $scan = scandir($dir);
        unset($scan[0], $scan[1]);

        foreach($scan as $entry) {
            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            $relativePath = substr($fullPath, strlen($this->source) + 1);

            if(is_dir($fullPath)) {
                $this->scanned["directories"][] = $relativePath;
                $this->scanDirectory($fullPath);
            } else {
                $this->scanned["files"][] = $relativePath;
            }
        }
    }

}