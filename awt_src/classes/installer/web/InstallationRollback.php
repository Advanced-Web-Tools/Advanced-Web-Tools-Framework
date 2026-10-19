<?php

namespace installer\web;

use PDO;

/** Compensating rollback for setup spread across HTTP requests (including MySQL DDL). */
final class InstallationRollback
{
    public static function tables(PDO $pdo): array
    {
        return array_column(self::objects($pdo), 'name');
    }

    private static function objects(PDO $pdo): array
    {
        $sql = match ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'sqlite' => "SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'",
            'pgsql' => "SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = current_schema()",
            default => 'SHOW FULL TABLES',
        };
        return array_map(static fn(array $row): array => ['name' => $row[0], 'type' => strtolower($row[1]) === 'view' ? 'VIEW' : 'TABLE'], $pdo->query($sql)->fetchAll(PDO::FETCH_NUM));
    }

    public static function snapshot(string $directory): array
    {
        if (!mkdir($directory, 0700, true)) throw new \RuntimeException('Could not create the rollback snapshot.');
        $paths = [PACKAGES, PUBLIC_STORAGE, CACHE];
        $snapshot = [];
        try {
            foreach ($paths as $index => $path) {
                $exists = file_exists($path) || is_link($path);
                if ($exists) self::copy($path, $directory . '/' . $index);
                $snapshot[] = ['path' => $path, 'backup' => $directory . '/' . $index, 'exists' => $exists];
            }
            $sqlite = ROOT . DIRECTORY_SEPARATOR . 'awt_db.sqlite';
            if (file_exists($sqlite)) {
                if (is_link($sqlite) || !copy($sqlite, $directory . '/sqlite')) {
                    throw new \RuntimeException('Could not back up the SQLite database.');
                }
            }
            return ['directory' => $directory, 'paths' => $snapshot, 'sqlite_existed' => file_exists($sqlite)];
        } catch (\Throwable $error) {
            self::remove($directory);
            throw $error;
        }
    }

    /** Only armed after confirming the target database is empty. */
    public static function restore(array $state, ?PDO $pdo): void
    {
        if (($state['database_armed'] ?? false) && $pdo !== null) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'mysql') $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            if ($driver === 'sqlite') $pdo->exec('PRAGMA foreign_keys = OFF');
            try {
                $objects = self::objects($pdo);
                usort($objects, static fn(array $a, array $b): int => ($b['type'] === 'VIEW') <=> ($a['type'] === 'VIEW'));
                foreach ($objects as $object) {
                    $quote = $driver === 'mysql' ? '`' : '"';
                    $name = $quote . str_replace($quote, $quote . $quote, $object['name']) . $quote;
                    $pdo->exec('DROP ' . $object['type'] . ' IF EXISTS ' . $name . ($driver === 'pgsql' ? ' CASCADE' : ''));
                }
            } finally {
                if ($driver === 'mysql') $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                if ($driver === 'sqlite') $pdo->exec('PRAGMA foreign_keys = ON');
            }
        }
        if (!isset($state['snapshot'])) {
            if (($state['sqlite_created'] ?? false) && is_file(ROOT . '/awt_db.sqlite') && !unlink(ROOT . '/awt_db.sqlite')) {
                throw new \RuntimeException('Could not remove the staged SQLite database.');
            }
            return;
        }
        $snapshot = $state['snapshot'];
        foreach ($snapshot['paths'] as $entry) {
            self::remove($entry['path']);
            if ($entry['exists']) self::copy($entry['backup'], $entry['path']);
        }
        if (($state['configuration']['database_type'] ?? '') === 'sqlite' && ($state['database_armed'] ?? false)) {
            $file = ROOT . DIRECTORY_SEPARATOR . 'awt_db.sqlite';
            foreach ([$file . '-wal', $file . '-shm', $file . '-journal'] as $sidecar) {
                if (is_file($sidecar) && !unlink($sidecar)) throw new \RuntimeException('Could not remove a SQLite journal.');
            }
            if ($snapshot['sqlite_existed']) {
                if (!copy($snapshot['directory'] . '/sqlite', $file)) throw new \RuntimeException('Could not restore SQLite.');
            } elseif (is_file($file) && !unlink($file)) {
                throw new \RuntimeException('Could not remove the staged SQLite database.');
            }
        }
    }

    public static function copy(string $source, string $destination): void
    {
        if (is_link($source)) {
            if (!symlink(readlink($source), $destination)) throw new \RuntimeException('Could not back up a symbolic link.');
        } elseif (is_dir($source)) {
            if (!is_dir($destination) && !mkdir($destination, 0755, true)) throw new \RuntimeException('Could not create a snapshot directory.');
            foreach (new \FilesystemIterator($source) as $entry) self::copy($entry->getPathname(), $destination . '/' . $entry->getFilename());
            chmod($destination, fileperms($source) & 0777);
        } elseif (!copy($source, $destination)) {
            throw new \RuntimeException('Could not copy a rollback file.');
        } else {
            chmod($destination, fileperms($source) & 0777);
        }
    }

    public static function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            if (!unlink($path)) throw new \RuntimeException('Could not remove an installation file.');
        } elseif (is_dir($path)) {
            foreach (new \FilesystemIterator($path) as $entry) self::remove($entry->getPathname());
            if (!rmdir($path)) throw new \RuntimeException('Could not remove an installation directory.');
        }
    }
}
