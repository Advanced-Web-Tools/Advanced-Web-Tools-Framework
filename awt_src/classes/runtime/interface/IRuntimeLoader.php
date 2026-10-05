<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: IRuntimeLoader.php
 * Created: 27/08/2026, 11:48
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\interface;

interface IRuntimeLoader
{
    public function load(): void;
    public function getLoaded(): array;
}