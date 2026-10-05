<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: IRuntimeCreator.php
 * Created: 27/08/2026, 11:47
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\interface;

use package\model\InstalledPackage;

interface IRuntimeCreator
{
    public function create(InstalledPackage $packages): IRuntime;
}