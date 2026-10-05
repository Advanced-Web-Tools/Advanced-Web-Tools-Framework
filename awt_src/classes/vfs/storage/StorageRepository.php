<?php

namespace vfs\storage;

use database\DatabaseManager;
use model\exceptions\ModelCRUDException;
use vfs\storage\interfaces\IStorageRepository;
use vfs\storage\enums\EOwnerType;

/**
 * Class StorageRepository
 *
 * Implements IStorageRepository (DIP). All database access is encapsulated
 * here so higher-level classes depend on the interface, not this class.
 * Method names are also standardised to match the interface contract.
 */
class StorageRepository implements IStorageRepository
{
    private DatabaseManager $database;

    public function __construct(?DatabaseManager $database = null)
    {
        $this->database = $database ?? new DatabaseManager();
    }

    // ----------------------------------------------------------------
    // IStorageRepository implementation
    // ----------------------------------------------------------------

    public function fetchAll(): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select()
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchById(int $id): StorageEntry
    {
        $rows = $this->database->table('awt_storage')->select()->where(['id' => $id])->get(1);
        if ($rows === []) throw new \OutOfBoundsException("Storage entry not found: {$id}");
        return $this->hydrateCollection($rows)[0];
    }

    public function fetchByOwner(int $ownerId): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select()
            ->where(['ownerId' => $ownerId])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchByName(string $name): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select()
            ->where(['name' => $name])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchByOwnerType(EOwnerType $ownerType): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select()
            ->where(['ownerType' => $ownerType->value])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchByOwnerTypeAndOwner(EOwnerType $ownerType, int $ownerId): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select()
            ->where(['ownerType' => $ownerType->value, 'ownerId' => $ownerId])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchByOwnerAndName(int $ownerId, string $name): ?StorageEntry
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select()
            ->where(['ownerId' => $ownerId, 'name' => $name])
            ->get();

        if (empty($rows)) {
            return null;
        }

        return $this->hydrateCollection($rows)[0];
    }

    /**
     * @throws ModelCRUDException
     */
    public function create(StorageEntry $entry): StorageEntry
    {
        $oldId = $entry->id;
        $oldUrl = $entry->url;
        try {
            return $this->database->transaction(function () use ($entry, $oldUrl): StorageEntry {
                $id = $this->database->table('awt_storage')->insert($this->data($entry))->executeInsert();
                if ($id === null) throw new \RuntimeException('Failed to register storage entry.');
                $entry->id = $id;
                $entry->setUrl($oldUrl);
                if (!$this->update($entry)) throw new \RuntimeException('Failed to persist storage URL.');
                $entry->setModelId($id);
                return $entry;
            });
        } catch (\Throwable $e) {
            $entry->id = $oldId;
            $entry->url = $oldUrl;
            if ($oldId !== null) $entry->setModelId($oldId);
            throw $e;
        }
    }

    public function update(StorageEntry $entry): bool
    {
        if ($entry->id === null) throw new \LogicException('Cannot update an unregistered storage entry.');
        return $this->database->table('awt_storage')->where(['id' => $entry->id])->update($this->data($entry));
    }

    public function delete(StorageEntry $entry): bool
    {
        if ($entry->id === null) return false;
        return $this->database->table('awt_storage')->where(['id' => $entry->id])->delete();
    }

    private function data(StorageEntry $entry): array
    {
        return [
            'name' => $entry->name, 'path' => $entry->path, 'url' => $entry->url,
            'size' => $entry->size, 'middleware' => $entry->middleware,
            'lastModified' => $entry->lastModified, 'ownerId' => $entry->ownerId,
            'ownerType' => $entry->ownerType instanceof EOwnerType ? $entry->ownerType->value : $entry->ownerType,
        ];
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Hydrates complete rows without additional database lookups.
     *
     * @param array $rows
     * @return StorageEntry[]
     */
    private function hydrateCollection(array $rows): array
    {
        return array_map(
            fn(array $row): StorageEntry => (new StorageEntry())->useConnectionFrom($this->database)->hydrateRow($row, 'awt_storage', 'id'),
            $rows
        );
    }
}
