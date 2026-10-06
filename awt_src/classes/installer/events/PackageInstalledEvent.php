<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: PackageInstalledEvent.php
 * Created: 06/10/2026, 13:10
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace installer\events;

use event\interfaces\IEvent;
use package\model\Package;

class PackageInstalledEvent implements IEvent
{

    private array $manifest;

    private ?int $id = null;

    public function setManifest(array $manifest)
    {
        $this->manifest = $manifest;
    }

    public function setId(int $id)
    {
        $this->id = $id;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return "package.installed";
    }

    /**
     * @inheritDoc
     */
    public function bundle(): array
    {
        return [
            "manifest" => $this->manifest,
            "id" => $this->id
        ];
    }
}