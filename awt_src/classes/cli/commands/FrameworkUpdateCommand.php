<?php

namespace cli\commands;

use cli\interfaces\CLICommand;
use installer\framework\FrameworkUpdater;
use Throwable;

final class FrameworkUpdateCommand implements CLICommand
{
    private string $message = '';
    private int $exitCode = 0;

    public function getCommand(): string { return 'framework_update'; }
    public function getHelp(): string { return 'Update the AWT framework from the latest GitHub release. Usage: php awt framework_update [--check]'; }
    public function getArguments(): array { return ['--check' => 'Show the installed and latest versions.']; }

    public function execute(string $command, array $args = []): void
    {
        $this->exitCode = 0;
        if ($args !== [] && $args !== ['--check']) {
            $this->message = $this->getHelp();
            $this->exitCode = 2;
            return;
        }
        try {
            $this->message = (new FrameworkUpdater(ROOT, FRAMEWORK_STORAGE . 'framework-update.lock'))->run($args !== []);
        } catch (Throwable $error) {
            $this->message = 'Framework update failed. ' . $error->getMessage();
            $this->exitCode = 1;
        }
    }

    public function getExitCode(): int { return $this->exitCode; }
    public function result(): string { return $this->message; }
}
