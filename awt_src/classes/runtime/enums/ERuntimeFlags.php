<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: ERuntimeFlags.php
 * Created: 27/08/2026, 12:25
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\enums;

enum ERuntimeFlags
{

    /**
     * The runtime will not be destroyed after execution, and will be made accessible to other runtimes within same package.
     * This flag can be within any runtime inside your package.
     */
    case CreatePassable;


    /**
     * Access other runtimes within the same package.
     * These runtimes must be executed before the runtime with this flag.
     * They must have `CreatePassable` flag set.
     * This flag can be within any runtime inside your package.
     */
    case AccessOtherInstances;

    /**
     * Marks a runtime that waits for another package. The scheduler resolves
     * environment waits before setup and setup waits before main, detecting
     * missing targets and cycles instead of relying on a retry limit.
     */
    case WaitForPackage;

    /**
     * Use it to get global event dispatcher.
     * This flag can be within any runtime inside your package.
     */
    case EventDispatcher;

    /**
     * Use it to get global shared data.
     * Shared data is accessible with `$this->shared[Package][data]` or `$this->getShared(package, data)`.
     * This flag can be within any runtime inside your package.
     */
    case UseShared;

    /**
     * Tells RuntimeExecutor that this package provides CLI commands.
     * This flag can be within any runtime inside your package.
     */
    case CommandProvider;

    /**
     * Tells RuntimeHandler that this package provides controllers or controller factories.
     * If you are not using the official ` RuntimeControllerAPI ` base class, you must implement `IRuntimeControllerCapabilities` interface.
     */
    case Controller;

    /**
     * Tells RuntimeExecutor that this package provides routers.
     * If you are not using the official ` RuntimeRouterAPI ` base class, you must implement `IRuntimeRouterCapabilities` interface.
     */
    case Router;


    /**
     * Tells RuntimeExecutor that this runtime is providing other runtimes.
     * If you are not using the official ` RuntimeLinkerAPI ` base class, you must implement `IRuntimeLinkerCapabilities` interface.
     * The best practice is to call '$this->createLink()' inside your runtimes `main()` method.
     */
    case RuntimeLinker;
}