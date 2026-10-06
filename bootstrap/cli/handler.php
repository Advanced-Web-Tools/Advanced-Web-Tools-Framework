<?php
ini_set('max_execution_time', 0);

global $routerManager;
global $loader;

use cli\CLIHandler;
use cli\commands\ClearCommand;
use cli\commands\HelloCommand;
use cli\commands\InstallCommand;
use cli\commands\PackageManagerCommand;
use cli\commands\RoutesCommand;
use cli\commands\VersionCommand;

global $cliHandler;
$cliHandler = new CLIHandler();

$_SERVER['REQUEST_URI'] = '/CLI/';

$cliHandler->addCommand(new InstallCommand());

if (DB_TYPE !== '') {
    $cliHandler->addCommand(new ClearCommand());
    $cliHandler->addCommand(new VersionCommand());
    $cliHandler->addCommand(new HelloCommand());
    $cliHandler->addCommand(new PackageManagerCommand());

    $loader->CLIHandler = $cliHandler;

    $rc = new RoutesCommand();
    $rc->addRoutes($routerManager->getRoutes());
    $cliHandler->addCommand($rc);
}
$argv = $_SERVER['argv'] ?? [];
array_shift($argv);
$firstCommand = $argv ? ['cmd' => array_shift($argv), 'args' => $argv] : null;

while (true) {
    if ($firstCommand !== null) {
        $cmd = $firstCommand['cmd'];
        $args = $firstCommand['args'];
        $firstCommand = null;
    } else {
        $input = readline("awt> ");
        if ($input === false) break;
        if (!$input) continue;

        try {
            $parts = $cliHandler->parseInput($input);
        } catch (\InvalidArgumentException $exception) {
            echo $exception->getMessage() . PHP_EOL;
            continue;
        }
        if ($parts === []) continue;
        $cmd = array_shift($parts);
        $args = $parts;
    }

    if ($cmd === 'help') {
        $cliHandler->help($args[0] ?? null);
        continue;
    }

    if ($cmd === 'clear') {
        echo "\033[2J\033[;H";
        continue;
    }

    $cliHandler->execute($cmd, $args);
}
