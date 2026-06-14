<?php

namespace installer\package;

use installer\interfaces\package\IPrepareInstallation;
use installer\interfaces\package\IVerifyManifest;
use installer\interfaces\package\IVerifyStructure;

readonly class PrepareInstallation implements IPrepareInstallation
{

    public function __construct(
        private IVerifyManifest $manifestVerifier,
        private IVerifyStructure $structureVerifier,
    ) {}

    /**
     * @inheritDoc
     */
    public function prepare(): IPrepareInstallation
    {
        $this->manifestVerifier
            ->addKey("name")
            ->addKey("version")
            ->addKey("type")
            ->addKey("minimum_awt_version");

        $this->structureVerifier
            ->addFile("main.php");

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function verify(): bool
    {
        $res = $this->structureVerifier->verify();
        if (!$res) {
            return false;
        }
        return $this->manifestVerifier->verify();
    }
}