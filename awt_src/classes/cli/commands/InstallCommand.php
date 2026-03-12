<?php

namespace cli\commands;

use cli\interfaces\CLICommand;

class InstallCommand implements CLICommand
{

    public function getCommand(): string
    {
        return 'install';
    }

    public function getHelp(): string
    {
        return "Installs a framework only works when awt_db.php is empty. To install arguments must be ran in the following order.";
    }

    public function getArguments(): array
    {
        return [
            "<database>" => "Database type",
            "<host>" => "Database host",
            "<username>" => "Database username",
            "<password>" => "Database password",
        ];
    }

    public function execute(string $command, array $args = []): void
    {
        // TODO: Implement execute() method.
    }

    /**
     * @inheritDoc
     */
    public function result(): string
    {
        // TODO: Implement result() method.
    }
}