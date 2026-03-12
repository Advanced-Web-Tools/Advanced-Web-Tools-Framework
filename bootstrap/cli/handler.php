<?php
ini_set('max_execution_time', 0);

global $routerManager;
global $loader;

use cli\CLIHandler;
use cli\commands\ClearCommand;
use cli\commands\HelloCommand;
use cli\commands\PackageManagerCommand;
use cli\commands\RoutesCommand;
use cli\commands\VersionCommand;

$handler = new CLIHandler();

$_SERVER['REQUEST_URI'] = '/CLI/';

$handler->addCommand(new ClearCommand());
$handler->addCommand(new VersionCommand());
$handler->addCommand(new HelloCommand());
$handler->addCommand(new PackageManagerCommand());

$loader->CLIHandler = $handler;

$rc = new RoutesCommand();
$rc->addRoutes($routerManager->getRoutes());

$handler->addCommand($rc);
$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

while (true) {
    if ($firstCommand !== null) {
        $cmd = $firstCommand['cmd'];
        $args = $firstCommand['args'];
        $firstCommand = null;
    } else {
        $input = readline("awt> ");
        if (!$input) continue;

        $parts = explode(" ", $input);
        $cmd = array_shift($parts);
        $args = $parts;
    }

    if ($cmd === 'help') {
        $handler->help($args[0] ?? null);
        continue;
    }

    if ($cmd === 'clear') {
        echo "\033[2J\033[;H";
        continue;
    }

    $handler->execute($cmd, $args);
}