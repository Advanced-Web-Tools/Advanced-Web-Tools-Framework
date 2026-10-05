<?php

namespace package\model;

use package\model\Package;

class InstalledPackage extends Package
{
    public int $id;
    public bool $status;
    public string $installation_date;

    public function getId(): int
    {
        return $this->id;
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    public function getInstallationDate(): string
    {
        return $this->installation_date;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }

    public function setInstallationDate(string $installation_date): void
    {
        $this->installation_date = $installation_date;
    }

}