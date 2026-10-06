<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: IRuntimeLinkerAPI.php
 * Created: 27/08/2026, 11:49
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\interface\api;

interface IRuntimeLinkerCapabilities extends IRuntimeBaseCapabilities
{
    public function getLinks(): array;

    public function createLink(string $name, string $path): void;
}