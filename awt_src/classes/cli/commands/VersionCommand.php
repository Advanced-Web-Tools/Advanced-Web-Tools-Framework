<?php


namespace cli\commands;


use cli\interfaces\CLICommand;
use installer\framework\FrameworkVersion;


class VersionCommand implements CLICommand
{
    private string $lastResult = '';


    public function getCommand(): string
    {
        return 'version';
    }


    public function getHelp(): string
    {
        return "Display current AWT version\nPrints application version.";
    }


    public function getArguments(): array
    {
        return [];
    }


    public function execute(string $command, array $args = []): void
    {
        $this->lastResult = FrameworkVersion::current();
    }


    public function result(): string
    {
        return $this->lastResult;
    }
}
