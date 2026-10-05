<?php

/**
 * Root directory of the application.
 */
if (defined('BASE_PATH')) {
    define("ROOT", rtrim(BASE_PATH, DIRECTORY_SEPARATOR));
} else {
    define("ROOT", dirname(__DIR__, 2));
}
/**
 * Source directory for the awt, specifically the 'awt_src' folder.
 */
const SRC = ROOT . DIRECTORY_SEPARATOR . 'awt_src' . DIRECTORY_SEPARATOR;

/**
 * Path to the classes directory within the source folder.
 */
const CLASSES = SRC . 'classes' . DIRECTORY_SEPARATOR;

/**
 * Path to the functions directory within the source folder.
 */
const FUNCTIONS = SRC . 'functions' . DIRECTORY_SEPARATOR;

/**
 * Path to the jobs directory within the source folder.
 */
const JOBS = SRC . 'jobs' . DIRECTORY_SEPARATOR;

/**
 * Main data directory for application data, located at 'awt_data'.
 */
const DATA = ROOT . DIRECTORY_SEPARATOR . 'awt_data' . DIRECTORY_SEPARATOR;

const STORAGE = DATA . 'storage' . DIRECTORY_SEPARATOR;
/**
 * Cache directory within the data directory for temporary cache files.
 */

const FRAMEWORK_STORAGE = STORAGE . 'framework' . DIRECTORY_SEPARATOR;
const PUBLIC_STORAGE = STORAGE . 'public' . DIRECTORY_SEPARATOR;
const CACHE = FRAMEWORK_STORAGE . 'cache' . DIRECTORY_SEPARATOR;
const COMPILED = CACHE . 'compiled' . DIRECTORY_SEPARATOR;
/**
 * Uploads directory within the data directory for uploaded files.
 */
const UPLOADS = PUBLIC_STORAGE . 'uploads' . DIRECTORY_SEPARATOR;

const CONFIG = DATA . 'config' . DIRECTORY_SEPARATOR;

/**
 * Temporary files directory within the data directory for temporary files.
 */
const TEMP = FRAMEWORK_STORAGE . 'temp' . DIRECTORY_SEPARATOR;

/**
 * Defines the packages directory in the root, likely for third-party or modular packages.
 */
const PACKAGES = ROOT . DIRECTORY_SEPARATOR . 'awt_packages' . DIRECTORY_SEPARATOR;
const PACKAGE_STORAGE = PUBLIC_STORAGE . 'packages' . DIRECTORY_SEPARATOR;
const PUBLIC_DIRECTORY = ROOT . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;
const ERRORS_DIRECTORY = PUBLIC_DIRECTORY . 'errors' . DIRECTORY_SEPARATOR;

if(!file_exists(CACHE)) mkdir(CACHE, 0755, true);
if(!file_exists(TEMP)) mkdir(TEMP, 0755, true);
if(!file_exists(UPLOADS)) mkdir(UPLOADS, 0755, true);
if(!file_exists(PACKAGE_STORAGE)) mkdir(PACKAGE_STORAGE, 0755, true);
if(!file_exists(COMPILED)) mkdir(COMPILED, 0755, true);