<?php

namespace package\model;

use package\model\Package;

class InstalledPackage extends Package
{
    public int $id;
    public bool $status = false;
    public string $installation_date = '';

    public function getInfo(): array
    {
        return array_merge(parent::getInfo(), [
            'id' => $this->id, 'installationDate' => $this->installation_date,
            'status' => $this->status ? 'Active' : 'Disabled',
        ]);
    }

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