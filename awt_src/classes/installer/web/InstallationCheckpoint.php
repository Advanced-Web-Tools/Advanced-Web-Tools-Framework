<?php

namespace installer\web;

use PDO;

/** Durable per-step backups, including DDL that MySQL cannot roll back. */
final class InstallationCheckpoint
{
    public static function capture(string $directory, PDO $pdo): array
    {
        $snapshot = InstallationRollback::snapshot($directory);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            // A plain file copy can miss committed WAL contents.
            unlink($directory . '/sqlite');
            $pdo->exec('VACUUM INTO ' . $pdo->quote($directory . '/sqlite'));
        } else {
            self::exportDatabase($directory, $pdo, $driver);
        }
        return ['snapshot' => $snapshot, 'driver' => $driver];
    }

    public static function restore(array $checkpoint, PDO $pdo, array $configuration): void
    {
        InstallationRollback::restore([
            'database_armed' => true, 'configuration' => $configuration,
            'snapshot' => $checkpoint['snapshot'],
        ], $pdo);
        if ($checkpoint['driver'] !== 'sqlite') self::importDatabase($checkpoint['snapshot']['directory'], $pdo, $checkpoint['driver']);
    }

    private static function identifier(string $name, string $driver): string
    {
        $quote = $driver === 'mysql' ? '`' : '"';
        return $quote . str_replace($quote, $quote . $quote, $name) . $quote;
    }

    private static function exportDatabase(string $directory, PDO $pdo, string $driver): void
    {
        $schema = ['tables' => [], 'views' => [], 'constraints' => [], 'indexes' => [], 'sequences' => [], 'triggers' => []];
        $objects = $pdo->query($driver === 'mysql' ? 'SHOW FULL TABLES'
            : 'SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = current_schema()')->fetchAll(PDO::FETCH_NUM);
        foreach ($objects as [$name, $type]) {
            $quoted = self::identifier($name, $driver);
            if (strtolower($type) === 'view') {
                if ($driver === 'mysql') $ddl = $pdo->query('SHOW CREATE VIEW ' . $quoted)->fetch(PDO::FETCH_NUM)[1];
                else {
                    $query = $pdo->prepare('SELECT pg_get_viewdef(CAST(? AS regclass), true)');
                    $query->execute([$quoted]);
                    $ddl = 'CREATE VIEW ' . $quoted . ' AS ' . $query->fetchColumn();
                }
                $schema['views'][] = $ddl;
                continue;
            }
            if ($driver === 'mysql') {
                $ddl = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(PDO::FETCH_NUM)[1];
                $columns = array_column(array_filter($pdo->query('SHOW FULL COLUMNS FROM ' . $quoted)->fetchAll(PDO::FETCH_ASSOC),
                    static fn(array $column): bool => !preg_match('/(?:VIRTUAL|STORED) GENERATED/', $column['Extra'])), 'Field');
            } else {
                [$ddl, $columns] = self::postgresTable($pdo, $name, $schema);
            }
            $index = count($schema['tables']);
            $binary = [];
            if ($driver === 'pgsql') {
                $query = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND udt_name = 'bytea'");
                $query->execute([$name]);
                $binary = $query->fetchAll(PDO::FETCH_COLUMN);
            }
            $schema['tables'][] = ['ddl' => $ddl, 'name' => $name, 'columns' => $columns, 'binary' => $binary];
            $file = fopen($directory . '/rows-' . $index, 'xb');
            if ($file === false) throw new \RuntimeException('Could not create the database checkpoint.');
            try {
                $query = $pdo->query('SELECT ' . implode(', ', array_map(static fn(string $column): string => self::identifier($column, $driver), $columns)) . ' FROM ' . $quoted);
                while (($row = $query->fetch(PDO::FETCH_NUM)) !== false) {
                    $line = json_encode(array_map(static function ($value) {
                        if ($value === null) return null;
                        if (is_resource($value)) $value = stream_get_contents($value);
                        if (is_bool($value)) $value = $value ? 'true' : 'false';
                        return base64_encode((string) $value);
                    }, $row), JSON_THROW_ON_ERROR) . "\n";
                    if (fwrite($file, $line) !== strlen($line)) throw new \RuntimeException('Could not write the database checkpoint.');
                }
            } finally { fclose($file); }
        }
        if ($driver === 'mysql') {
            foreach ($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $trigger) {
                $row = $pdo->query('SHOW CREATE TRIGGER ' . self::identifier($trigger['Trigger'], $driver))->fetch(PDO::FETCH_ASSOC);
                $schema['triggers'][] = $row['SQL Original Statement'];
            }
        }
        $json = json_encode($schema, JSON_THROW_ON_ERROR);
        if (file_put_contents($directory . '/schema.json', $json) !== strlen($json)) throw new \RuntimeException('Could not save the database checkpoint.');
    }

    private static function postgresTable(PDO $pdo, string $name, array &$schema): array
    {
        $quoted = self::identifier($name, 'pgsql');
        $query = $pdo->prepare("SELECT a.attname, format_type(a.atttypid, a.atttypmod) AS type, a.attnotnull, a.attidentity, a.attgenerated, pg_get_expr(d.adbin, d.adrelid) AS expression
            FROM pg_attribute a LEFT JOIN pg_attrdef d ON a.attrelid = d.adrelid AND a.attnum = d.adnum
            WHERE a.attrelid = CAST(? AS regclass) AND a.attnum > 0 AND NOT a.attisdropped ORDER BY a.attnum");
        $query->execute([$quoted]);
        $definitions = $columns = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $definition = self::identifier($column['attname'], 'pgsql') . ' ' . $column['type'];
            if ($column['attidentity'] !== '') $definition .= ' GENERATED BY DEFAULT AS IDENTITY';
            elseif ($column['attgenerated'] !== '') $definition .= ' GENERATED ALWAYS AS (' . $column['expression'] . ') STORED';
            elseif ($column['expression'] !== null) $definition .= ' DEFAULT ' . $column['expression'];
            if ($column['attnotnull']) $definition .= ' NOT NULL';
            $definitions[] = $definition;
            if ($column['attgenerated'] === '') $columns[] = $column['attname'];
            // AWT uses identity columns. Preserve their next values after restoring rows.
            if ($column['attidentity'] !== '') {
                $sequenceQuery = $pdo->prepare('SELECT pg_get_serial_sequence(?, ?)');
                $sequenceQuery->execute([$quoted, $column['attname']]);
                $sequence = $sequenceQuery->fetchColumn();
                $sequenceState = $pdo->query('SELECT last_value, is_called FROM ' . $sequence)->fetch(PDO::FETCH_ASSOC);
                $schema['sequences'][] = [$quoted, $column['attname'], $sequenceState['last_value'], (bool) $sequenceState['is_called']];
            }
        }
        $query = $pdo->prepare('SELECT conname, contype, pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid = CAST(? AS regclass)');
        $query->execute([$quoted]);
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $constraint) {
            $schema['constraints'][] = ['foreign' => $constraint['contype'] === 'f', 'ddl' => 'ALTER TABLE ' . $quoted . ' ADD CONSTRAINT ' . self::identifier($constraint['conname'], 'pgsql') . ' ' . $constraint['definition']];
        }
        $query = $pdo->prepare('SELECT pg_get_indexdef(indexrelid) FROM pg_index WHERE indrelid = CAST(? AS regclass) AND indexrelid NOT IN (SELECT conindid FROM pg_constraint WHERE conrelid = CAST(? AS regclass))');
        $query->execute([$quoted, $quoted]);
        array_push($schema['indexes'], ...$query->fetchAll(PDO::FETCH_COLUMN));
        $query = $pdo->prepare('SELECT pg_get_triggerdef(oid) FROM pg_trigger WHERE tgrelid = CAST(? AS regclass) AND NOT tgisinternal');
        $query->execute([$quoted]);
        array_push($schema['triggers'], ...$query->fetchAll(PDO::FETCH_COLUMN));
        return ['CREATE TABLE ' . $quoted . ' (' . implode(', ', $definitions) . ')', $columns];
    }

    private static function importDatabase(string $directory, PDO $pdo, string $driver): void
    {
        $schema = json_decode(file_get_contents($directory . '/schema.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($driver === 'mysql') $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($schema['tables'] as $table) $pdo->exec($table['ddl']);
            foreach ($schema['tables'] as $index => $table) {
                $columns = array_map(static fn(string $column): string => self::identifier($column, $driver), $table['columns']);
                $statement = $pdo->prepare('INSERT INTO ' . self::identifier($table['name'], $driver) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
                $file = fopen($directory . '/rows-' . $index, 'rb');
                if ($file === false) throw new \RuntimeException('Could not read the database checkpoint.');
                try {
                    while (($line = fgets($file)) !== false) {
                        $values = array_map(static fn($value) => $value === null ? null : base64_decode($value, true), json_decode($line, true, 512, JSON_THROW_ON_ERROR));
                        foreach ($values as $position => $value) {
                            $type = $value === null ? PDO::PARAM_NULL : (in_array($table['columns'][$position], $table['binary'], true) ? PDO::PARAM_LOB : PDO::PARAM_STR);
                            $statement->bindValue($position + 1, $value, $type);
                        }
                        $statement->execute();
                    }
                } finally { fclose($file); }
            }
            usort($schema['constraints'], static fn(array $a, array $b): int => $a['foreign'] <=> $b['foreign']);
            foreach ($schema['constraints'] as $constraint) $pdo->exec($constraint['ddl']);
            foreach ($schema['indexes'] as $ddl) $pdo->exec($ddl);
            foreach ($schema['sequences'] as [$table, $column, $value, $called]) {
                $statement = $pdo->prepare('SELECT setval(pg_get_serial_sequence(?, ?), ?, ?)');
                $statement->execute([$table, $column, $value, $called ? 'true' : 'false']);
            }
            $views = $schema['views'];
            while ($views !== []) {
                $pending = [];
                foreach ($views as $ddl) {
                    try { $pdo->exec($ddl); }
                    catch (\PDOException $error) { $pending[] = $ddl; }
                }
                if (count($pending) === count($views)) throw new \RuntimeException('Could not restore checkpoint views.');
                $views = $pending;
            }
            foreach ($schema['triggers'] as $ddl) $pdo->exec($ddl);
        } finally {
            if ($driver === 'mysql') $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
