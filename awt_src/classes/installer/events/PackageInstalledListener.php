<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: PackageInstalledListener.php
 * Created: 06/10/2026, 13:12
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace installer\events;

use event\interfaces\IEvent;
use event\interfaces\IEventListener;

class PackageInstalledListener implements IEventListener
{

    /**
     * @inheritDoc
     */
    public function handle(IEvent $event): array
    {
        return $event->bundle();
    }
}