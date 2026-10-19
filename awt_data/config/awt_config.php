<?php
const AWT_VERSION = "27.1.2";
const DEBUG = true;
const SHOW_SQL_CONNECTIONS_CALLS = false;

/**
 * # WARNING: Setting this to TRUE enables remote package installations.
 *
 * This setting is intended for development purposes only.
 * It MUST remain FALSE in production environments to ensure security.
 *
 * In order for this to work, please also set `DEBUG = true`.
 */
const REMOTE_INSTALL_FOR_DEVS = false;
const DEV_SECRET = "12345678";

// Upload limits must be applied before PHP parses the request.
// See public/.user.ini (CGI/FPM), public/.htaccess (mod_php), and README.md.
ini_set("max_execution_time", "300");

if (DEBUG) {
    ini_set('display_startup_errors', 1);
    ini_set('display_errors', 1);
    error_reporting(E_ALL ^ E_DEPRECATED ^ E_WARNING ^ E_NOTICE);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
