<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: IPackageUninstaller.php
 * Created: 06/10/2026, 18:34
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace installer\interfaces\package;

interface IPackageUninstaller
{
    public function uninstall(int $id): bool;
}