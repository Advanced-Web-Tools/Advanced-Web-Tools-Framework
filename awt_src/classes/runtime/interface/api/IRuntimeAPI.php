<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: IRuntimeAPI.php
 * Created: 27/08/2026, 16:29
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\interface\api;

use runtime\interface\IRuntime;

/**
 * Provides a set of methods that are called by the RuntimeExecutor to set up and execute the runtime.
 */
interface IRuntimeAPI
{
    // Properties

    /*
     * Interface properties are only available since php 8.4
     * Not sure if should be used or not.
     * It's better to force the user to use methods instead.
     */


    // Methods
    /**
     * @return IRuntime
     */
    public function getRuntime(): IRuntime;

    /**
     * This method is called by RuntimeExecutor to set the runtime information.
     * @param IRuntime $runtime Base runtime object containing universal information about the runtime.
     * @return void
     */
    public function setInfo(IRuntime $runtime): void;

    /**
     * This method is called by RuntimeExecutor to set up the runtime environment.
     * Should be used to set `RuntimeFlags`, `waitForPackages` and `addCommand`.
     * If another developer is modifying this method through inheritance, make sure to call the parent method.
     * @return void
     */
    public function environmentSetup(): void;

    /**
     * This method is called by RuntimeExecutor to prepare the `main()` method.
     * This method can be empty; its primary purpose is to provide a way for a developer to create a custom setup without modifying the base class `environmentSetup()`.
     * Examples:
     * - Create factories
     * - Create routes
     * - Create controllers
     * - Create and add event listeners
     * @return void
     */
    public function setup(): void;

    /**
     * This method is called by RuntimeExecutor to execute the main logic of the runtime.
     *
     * Can be empty if the runtime only provides dependencies to other packages.
     * When implemented, it should contain the runtime's core purposes and logic.
     *
     * Examples:
     * - Register controllers
     * - Register routes
     * - Link other runtimes
     * - Dispatch events
     *
     * @return void
     */
    public function main(): void;
}