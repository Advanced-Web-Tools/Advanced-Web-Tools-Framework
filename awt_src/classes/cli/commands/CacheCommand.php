<?php

namespace cli\commands;

use cli\interfaces\CLICommand;
use vfs\cache\CacheMaintenance;

class CacheCommand implements CLICommand
{
    private string $lastResult = '';

    public function __construct(private readonly CacheMaintenance $cache = new CacheMaintenance()) {}

    public function getCommand(): string { return 'cache'; }

    public function getHelp(): string
    {
        return 'Clear framework caches or rebuild VFS package resource maps.';
    }

    public function getArguments(): array
    {
        return [
            'clear' => 'Remove generated framework caches. Usage: cache clear',
            'rebuild' => 'Clear caches and rebuild VFS resource maps. Usage: cache rebuild',
        ];
    }

    public function execute(string $command, array $args = []): void
    {
        if (count($args) !== 1 || !in_array($args[0], ['clear', 'rebuild'], true)) {
            $this->lastResult = 'Usage: cache <clear|rebuild>';
            return;
        }
        try {
            $this->lastResult = $args[0] === 'clear'
                ? sprintf('Cache cleared (%d files removed).', $this->cache->clear())
                : sprintf('Cache rebuilt (%d VFS package resource maps). Database query caches refill on demand.', $this->cache->rebuild());
        } catch (\Throwable $error) {
            $this->lastResult = 'Cache ' . $args[0] . ' failed: ' . $error->getMessage();
        }
    }

    public function result(): string { return $this->lastResult; }
}
