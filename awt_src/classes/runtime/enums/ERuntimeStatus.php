<?php
/*
 *  Copyright (c) 2026 Stefan Crkvenjakov
 *  Username: stefan
 *  GitHub: https://github.com/ElStefanos
 *
 * File: ERuntimeStatus.php
 * Created: 27/08/2026, 12:06
 *
 * This file is part of the Advanced-Web-Tools-Framework project.
 * All rights reserved.
 *
 *
 */

namespace runtime\enums;

enum ERuntimeStatus
{
    case NOT_STARTED;
    case RUNNING;
    case IGNORED;
    case STOPPED;
    case PAUSED;
    case EXECUTED;
}
