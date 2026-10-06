<?php

namespace uninstaller\cleanup;

use database\creator\interface\ITableRegistry;
use database\creator\interface\ITableSchemaProvider;
use uninstaller\interfaces\IPackageCleanup;

class TableCleanup implements IPackageCleanup
{
    public function __construct(
        private readonly ITableRegistry $registry,
        private readonly ITableSchemaProvider $schema,
    ) {}

    public function clean(int $id, string $name): array
    {
        $pending = array_fill_keys($this->registry->findByOwner($id), '');
        do {
            $progress = false;
            foreach (array_keys($pending) as $table) {
                try {
                    $quoted = '`' . str_replace('`', '``', $table) . '`';
                    // Allows retry after successful DDL but failed registry cleanup.
                    if (!$this->schema->executeDDL("DROP TABLE IF EXISTS {$quoted}")) {
                        throw new \RuntimeException('Table drop failed.');
                    }
                    $this->registry->unregisterTable($table);
                    unset($pending[$table]);
                    $progress = true;
                } catch (\Throwable $error) {
                    $pending[$table] = "Table {$table}: " . $error->getMessage();
                }
            }
            // Retry failed drops after referencing tables have been removed.
        } while ($progress && $pending !== []);
        return array_values($pending);
    }
}
