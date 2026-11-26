<?php

namespace cli\commands;

use automation\AutomationHandler;
use cli\interfaces\CLICommand;

final class AutomationCommand implements CLICommand
{
    private int $exitCode = 0;

    public function __construct(
        private readonly ?AutomationHandler $automation,
        private readonly string $lockPath,
    ) {}

    public function getCommand(): string
    {
        return 'automation';
    }

    public function getHelp(): string
    {
        return 'Run scheduled package jobs. Usage: php awt automation run';
    }

    public function getArguments(): array
    {
        return ['run' => 'Execute jobs due in the current minute.'];
    }

    public function execute(string $command, array $args = []): void
    {
        $this->exitCode = 0;
        if ($args !== ['run']) {
            fwrite(STDERR, 'Usage: php awt automation run' . PHP_EOL);
            $this->exitCode = 2;
            return;
        }
        if ($this->automation === null) {
            fwrite(STDERR, 'Automation requires an installed AWT instance.' . PHP_EOL);
            $this->exitCode = 1;
            return;
        }

        $lock = @fopen($this->lockPath, 'c');
        if ($lock === false) {
            fwrite(STDERR, 'Cannot open the automation lock.' . PHP_EOL);
            $this->exitCode = 1;
            return;
        }
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB, $wouldBlock)) {
                if (!$wouldBlock) {
                    fwrite(STDERR, 'Cannot acquire the automation lock.' . PHP_EOL);
                    $this->exitCode = 1;
                }
                return;
            }
            foreach ($this->automation->runDue(new \DateTimeImmutable()) as $failure) {
                fwrite(STDERR, $failure . PHP_EOL);
                $this->exitCode = 1;
            }
        } finally {
            fclose($lock);
        }
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function result(): string
    {
        return '';
    }
}
