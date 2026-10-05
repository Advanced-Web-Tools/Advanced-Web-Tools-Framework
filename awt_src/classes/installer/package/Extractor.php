<?php

namespace installer\package;

use installer\interfaces\package\IExtractor;
use RuntimeException;
use vfs\transient\interfaces\ITransientStorageEntry;
use ZipArchive;

class Extractor implements IExtractor
{


    public function __construct(
        private ZipArchive $zip,
        private ITransientStorageEntry $target,
        private string $destination
    ){}

    /**
     * @inheritDoc
     * @throws \packages\exceptions\RuntimeException
     */
    public function extract(): void
    {
        $this->destination .= hash("md5", $this->target->getName());
        $this->makeDestination();

        $this->zip->open($this->target->getPath());
        $this->zipVerify();

        $this->zip->extractTo($this->destination);
    }

    /**
     * @inheritDoc
     */
    public function getZip(): ZipArchive
    {
        return $this->zip;
    }

    /**
     * @inheritDoc
     */
    public function getTarget(): ITransientStorageEntry
    {
        return $this->target;
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
    public function setTarget(ITransientStorageEntry $target): void
    {
        $this->target = $target;
    }

    /**
     * @inheritDoc
     */
    public function setDestination(string $destination): void
    {
        $this->destination = $destination;
    }

    /**
     * @inheritDoc
     */
    public function zipVerify(): void
    {
        if($this->zip->status !== ZipArchive::ER_OK){
            throw new RuntimeException("There was an error extracting the package. ZIP status: {$this->zip->status}");
        }
    }


    //Helpers

    private function makeDestination(): void
    {
        if(!is_dir($this->destination) && !mkdir($concurrentDirectory = $this->destination, 0755, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
    }

}