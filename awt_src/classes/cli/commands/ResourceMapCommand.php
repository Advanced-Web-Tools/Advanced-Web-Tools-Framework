<?php

namespace cli\commands;

use cli\interfaces\CLICommand;
use vfs\cache\CacheMaintenance;

final class ResourceMapCommand implements CLICommand
{
    private string $lastResult = '';

    public function __construct(private readonly CacheMaintenance $cache = new CacheMaintenance()) {}

    public function getCommand(): string
    {
        return 'resource_map';
    }

    public function getHelp(): string
    {
        return 'Rebuild or clear VFS resource maps for one package or all packages.';
    }

    public function getArguments(): array
    {
        return [
            'rebuild [package]' => 'Rebuild resource maps for the named package or all packages.',
            'clear [package]' => 'Clear the named package resource map or the entire resource map cache.',
        ];
    }

    public function execute(string $command, array $args = []): void
    {
        $action = $args[0] ?? '';
        $packageName = $args[1] ?? null;
        if (!in_array($action, ['rebuild', 'clear'], true) || count($args) > 2 || $packageName === '') {
            $this->lastResult = 'Usage: resource_map <rebuild|clear> [package]';
            return;
        }

        try {
            if ($action === 'rebuild') {
                $count = $this->cache->rebuildResourceMaps($packageName);
                $this->lastResult = $packageName === null
                    ? "Rebuilt {$count} package resource maps."
                    : "Rebuilt the resource map for {$packageName}.";
            } else {
                $this->cache->clearResourceMaps($packageName);
                $this->lastResult = $packageName === null
                    ? 'Cleared all package resource maps.'
                    : "Cleared the resource map for {$packageName}.";
            }
        } catch (\Throwable $error) {
            $this->lastResult = "Resource map {$action} failed: " . $error->getMessage();
        }
    }

    public function result(): string
    {
        return $this->lastResult;
    }
}
