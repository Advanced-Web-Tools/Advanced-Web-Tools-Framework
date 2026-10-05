<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: IRuntimeBaseUtilsAPI.php
 * Created: 27/08/2026, 14:14
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\interface\api;

use cli\interfaces\CLICommand;
use runtime\enums\ERuntimeFlags;

interface IRuntimeBaseCapabilities
{
    /**
     * Use to tell RuntimeExecutor what this runtime provides and needs.
     * This information should be stored inside `IRuntime`.
     * @param ERuntimeFlags $flag
     * @return void
     */
    public function setRuntimeFlag(ERuntimeFlags $flag): void;

    /**
     * Use to tell RuntimeExecutor for which package this package needs to wait to be executed.
     * This information should be stored inside IRuntime.
     * @param string $package Name of the package to be waited for. Missing or disabled targets and dependency cycles stop execution with a diagnostic.
     * @return void
     */
    public function waitForPackage(string $package): void;

    /**
     * Add a single item to the shared list.
     * Can be used to update a shared item.
     * Only works if `ERuntimeFlags::UseShared` is set.
     * @param string $name Name of the item to be accessed by.
     * @param mixed $share The shared item.
     * @return void
     */
    public function setShared(string $name, mixed $share): void;


    /**
     * Get a shared item from the shared list.
     * Only works if `ERuntimeFlags::UseShared` is set.
     * @param string $name Name of the package that is providing a shared item.
     * @param string $shared Name of the shared item.
     * @param string|null $expectedType Expected type of the shared item. If null, any type is allowed.
     * @return mixed
     */
    public function getShared(string $name, string $shared, string $expectedType = null) : mixed;

    /**
     * Get a passable object from other runtimes within same package.
     * Only works if `ERuntimeFlags::AccessOtherInstances` is set.
     * @param string $className
     * @return object|null
     */
    public function getPassable(string $className): ?object;

    /**
     * Returns a declared class from the file. Only works withing the same package.
     * @param string $pathFromRoot The relative path to the class file. Example: "classes/MyClass.php"
     * @return object|null
     */
    public function getLocalObject(string $pathFromRoot): ?object;

    /**
     * Add a command to the CLI.
     * Only works if `ERuntimeFlags::CommandProvider` is set.
     * Only works if the php is running in CLI mode.
     * @param CLICommand $command
     * @return void
     */
    public function addCommand(CLICommand $command): void;
}