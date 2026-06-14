<?php

namespace database\cache;

use database\interface\ICache;
use vfs\cache\Cache;
use vfs\cache\enums\ECacheValidation;
use vfs\transient\enums\ETransientType;
use vfs\transient\interfaces\ITransientStorage;
use vfs\transient\interfaces\ITransientStorageEntry;
use vfs\transient\TransientStorage;

/**
 * DbCache
 *
 * Implements CacheInterface backed by the vfs Cache + TransientStorage layers.
 *
 * Cache invalidation is condition-aware: each cached query is stored alongside
 * the raw WHERE column/value pairs that produced it.  A subsequent CUD
 * operation supplies its own WHERE conditions, and only the cached queries
 * whose conditions overlap are evicted — rather than blindly evicting by row ID.
 *
 * This means UPDATE … WHERE name = 'Alice' correctly flushes every SELECT
 * that was filtered by name = 'Alice', without touching unrelated cache
 * entries for the same table.
 *
 * Index structure (stored per table in linked_conditions.php):
 *
 *   [
 *     'SELECT …' => [
 *       'column' => 'name',
 *       'value'  => 'Alice',
 *     ],
 *     'SELECT …' => [],    // empty = full-table scan, always invalidated
 *     …
 *   ]
 */
class DatabaseCache implements ICache
{
    private const INDEX_FILENAME = 'linked_conditions';
    private const INDEX_FILE     = 'linked_conditions.php';

    private Cache $cache;

    public function __construct()
    {
        $this->cache = new Cache();
    }

    // -------------------------------------------------------------------------
    // CacheInterface
    // -------------------------------------------------------------------------

    /** {@inheritdoc} */
    public function get(string $table, string $query): array|false
    {
        $result = $this->cache->pool($table)->getCache($query);
        return is_array($result) ? $result : false;
    }

    /** {@inheritdoc} */
    public function set(string $table, string $query, array $result, array $conditions): void
    {
        // 1. Store the result rows.
        $this->cache->pool($table)->createConfig(ECacheValidation::EXPIRE, [])->setCache($query, $result);

        // 2. Update the condition index so we can later invalidate by column/value.
        $storage = $this->makeStorage($table);
        $index   = $this->loadIndex($storage);

        $index[$query] = $conditions;   // column → value map (may be empty for full-table SELECTs)

        $this->saveIndex($storage, $index);
    }

    /** {@inheritdoc} */
    public function invalidate(string $table, array $cudConditions): void
    {
        $storage = $this->makeStorage($table);
        $index   = $this->loadIndex($storage);

        if (empty($index)) {
            return;
        }

        $pool = $this->cache->pool($table);

        foreach ($index as $query => $cachedConditions) {
            if ($this->shouldInvalidate($cachedConditions, $cudConditions)) {
                $pool->deleteCache($query);
                unset($index[$query]);
            }
        }

        $this->saveIndex($storage, $index);
    }

    /** {@inheritdoc} */
    public function invalidateTable(string $table): void
    {
        $storage = $this->makeStorage($table);
        $index   = $this->loadIndex($storage);
        $pool    = $this->cache->pool($table);

        foreach (array_keys($index) as $query) {
            $pool->deleteCache($query);
        }

        $this->saveIndex($storage, []);
    }

    // -------------------------------------------------------------------------
    // Invalidation logic
    // -------------------------------------------------------------------------

    /**
     * Decide whether a cached query should be evicted given the CUD conditions.
     *
     * @param array $cachedConditions  column → value map recorded when the query was cached.
     * @param array $cudConditions     column → value map from the CUD WHERE clause.
     */
    private function shouldInvalidate(array $cachedConditions, array $cudConditions): bool
    {
        // Bulk CUD (no WHERE) → everything on this table is stale.
        if (empty($cudConditions)) {
            return true;
        }

        // Full-table SELECT (no WHERE when cached) → always stale after any CUD.
        if (empty($cachedConditions)) {
            return true;
        }

        // Overlap check: invalidate if any column+value pair matches.
        foreach ($cudConditions as $column => $value) {
            if (
                array_key_exists($column, $cachedConditions) &&
                $cachedConditions[$column] == $value
            ) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Index persistence helpers
    // -------------------------------------------------------------------------

    private function makeStorage(string $table): ITransientStorage
    {
        $storage = new TransientStorage();
        $storage->setPool('cache')->setSubPool($table);
        return $storage;
    }

    /**
     * Load the condition index for a table.
     * Returns an empty array when no index file exists yet.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadIndex(ITransientStorage $storage): array
    {
        $file = $storage->getFile(self::INDEX_FILE);

        if ($file === null) {
            return [];
        }

        $data = require $file->getPath();
        return is_array($data) ? $data : [];
    }

    /**
     * Persist the condition index back to disk.
     *
     * Creates the file on first write; overwrites on subsequent writes.
     *
     * @param array<string, array<string, mixed>> $index
     */
    private function saveIndex(ITransientStorage $storage, array $index): void
    {
        $export  = '<?php return ' . var_export($index, true) . ';';
        $file    = $storage->getFile(self::INDEX_FILE);

        if ($file === null) {
            $storage->createFile(self::INDEX_FILENAME, ETransientType::PHP, $export);
        } else {
            $file->write($index);
        }
    }
}
