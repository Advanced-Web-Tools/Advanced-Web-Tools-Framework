<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: IRuntime.php
 * Created: 27/08/2026, 11:44
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\interface;

use event\EventDispatcher;
use package\model\InstalledPackage;
use runtime\enums\ERuntimeStatus;

interface IRuntime
{
    public static function create(?InstalledPackage $package): IRuntime;
    public function getEventDispatcher(): EventDispatcher;

    public function setEventDispatcher(EventDispatcher $eventDispatcher): void;
    /**
     * Returns the identifier of the runtime.
     * @return int
     */
    public function getRuntimeId(): int;

    public function getRuntimeName(): string;

    public function setRuntimeStatus(ERuntimeStatus $status): void;

    public function getRuntimeStatus(): ERuntimeStatus;

    public function getPackage(): InstalledPackage;

    public function getRootPath(): string;

    public function setFlags (array $flags): void;

    public function getFlags(): array;

    public function setWaitFor(array $package): void;

    public function getWaitFor(): array;

    public function setPassable(array $passable): void;
    public function getPassable(string $name): array;
    public function setSharedRegistry(array $shared): void;
    public function getSharedRegistry(): array;
}