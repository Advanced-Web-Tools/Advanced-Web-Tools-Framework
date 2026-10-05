<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: IRuntimeControllerAPI.php
 * Created: 27/08/2026, 11:49
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\interface\api;

interface IRuntimeControllerCapabilities extends IRuntimeBaseCapabilities
{
    public function getController(string $name): \object\ObjectFactory|\controller\Controller;
}