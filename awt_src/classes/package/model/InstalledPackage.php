<?php

namespace package\model;

use package\model\Package;

class InstalledPackage extends Package
{
    public int $id;
    public bool $status = false;

    public function getInfo(): array
    {
        return array_merge(parent::getInfo(), [
            'id' => $this->id,
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

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }


}
