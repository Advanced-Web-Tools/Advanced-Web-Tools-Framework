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

    public function __construct()
    {
        $this->database = new DatabaseManager();
    }

    // ----------------------------------------------------------------
    // IStorageRepository implementation
    // ----------------------------------------------------------------

    public function fetchAll(): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select(['id'])
            ->where(['1' => '1'])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchById(int $id): StorageEntry
    {
        return new StorageEntry($id);
    }

    public function fetchByOwner(int $ownerId): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select(['id'])
            ->where(['ownerId' => $ownerId])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchByName(string $name): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select(['id'])
            ->where(['name' => $name])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchByOwnerType(EOwnerType $ownerType): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select(['id'])
            ->where(['ownerType' => $ownerType->value])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchByOwnerTypeAndOwner(EOwnerType $ownerType, int $ownerId): array
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select(['id'])
            ->where(['ownerType' => $ownerType->value, 'ownerId' => $ownerId])
            ->get();

        return $this->hydrateCollection($rows);
    }

    public function fetchByOwnerAndName(int $ownerId, string $name): ?StorageEntry
    {
        $rows = $this->database
            ->table('awt_storage')
            ->select(['id'])
            ->where(['ownerId' => $ownerId, 'name' => $name])
            ->get();

        if (empty($rows)) {
            return null;
        }

        return new StorageEntry($rows[0]['id']);
    }

    /**
     * @throws ModelCRUDException
     */
    public function create(StorageEntry $entry): StorageEntry
    {
        var_dump($entry->getOwnerId());
        $id = $entry->saveModel();
        $entry->setModelId($id);
        $entry->setUrl();
        $entry->save();
        return $entry;
    }

    /**
     * @throws ModelCRUDException
     */
    public function update(StorageEntry $entry): bool
    {
        return $entry->save();
    }

    /**
     * @throws ModelCRUDException
     */
    public function delete(StorageEntry $entry): bool
    {
        return $entry->deleteModel();
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    /**
     * Converts a flat array of ['id' => x] rows into StorageEntry objects.
     *
     * @param array $rows
     * @return StorageEntry[]
     */
    private function hydrateCollection(array $rows): array
    {
        return array_map(
            static fn(array $row): StorageEntry => new StorageEntry($row['id']),
            $rows
        );
    }
}
