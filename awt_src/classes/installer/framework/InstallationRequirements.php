<?php

namespace installer\framework;

/** Shared preflight for CLI and web installations. */
final class InstallationRequirements
{
    public static function checks(?string $databaseType = null): array
    {
        $checks = ['PHP 8.3 or later' => PHP_VERSION_ID >= 80300];
        foreach (['PDO', 'dom', 'fileinfo', 'session', 'curl', 'zip', 'openssl', 'json', 'ctype', 'filter'] as $extension) {
            $checks['PHP ' . $extension] = extension_loaded($extension);
        }
        $checks['Supported PDO driver'] = extension_loaded('PDO') && array_intersect(['sqlite', 'mysql', 'pgsql'], \PDO::getAvailableDrivers()) !== [];
        if ($databaseType !== null) {
            $driver = match ($databaseType) {
                'sqlite' => 'sqlite', 'mysql' => 'mysql', 'postgresql' => 'pgsql',
                default => throw new \InvalidArgumentException('Unsupported database type.'),
            };
            $checks['PDO ' . $driver] = extension_loaded('PDO') && in_array($driver, \PDO::getAvailableDrivers(), true);
        }
        return $checks;
    }

    public static function assertSatisfied(?string $databaseType = null): void
    {
        $missing = array_keys(array_filter(self::checks($databaseType), static fn(bool $enabled): bool => !$enabled));
        if ($missing !== []) {
            throw new \RuntimeException('Enable the following requirements before continuing: ' . implode(', ', $missing) . '.');
        }
    }
}
