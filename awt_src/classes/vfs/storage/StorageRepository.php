<?php

namespace vfs\storage;

use database\DatabaseManager;
use vfs\storage\enums\EOwnerType;

class StorageRepository
{
    private DatabaseManager $database;
    public function __construct()
    {
        $this->database = new DatabaseManager();
    }

    public function fetchAllEntries(): array
    {
        $res = $this->database->table('awt_storage')->select(["id"])->where(["1" => "1"])->get();

        $result = [];

        foreach($res as $r) {
            $result[] = new StorageEntry($r['id']);
        }

        return $result;
    }

    public function fetchEntryById(int $id): StorageEntry
    {
        return new StorageEntry($id);
    }

    public function createEntry(StorageEntry $entry): StorageEntry
    {
        $entry->saveModel();
        return $entry;
    }

    public function deleteEntry(StorageEntry $entry): bool
    {
        return $entry->delete();
    }


    public function fetchByOwner(int $ownerId): array
    {
        $res = $this->database->table('awt_storage')->select(["id"])->where(["ownerId" => $ownerId])->get();

        $result = [];

        foreach($res as $r) {
            $result[] = new StorageEntry($r['id']);
        }

        return $result;
    }

    public function fetchByName(string $name): array
    {
        $res = $this->database->table('awt_storage')->select(["id"])->where(["name" => $name])->get();
        $result = [];

        foreach($res as $r) {
            $result[] = new StorageEntry($r['id']);
        }

        return $result;
    }

    public function fetchByOwnerType(EOwnerType $ownerType): array
    {
        $res = $this->database->table('awt_storage')->select(["id"])->where(["ownerType" => $ownerType->value])->get();
        $result = [];
        foreach($res as $r) {
            $result[] = new StorageEntry($r['id']);
        }
        return $result;
    }

    public function fetchByOwnerTypeAndOwner(EOwnerType $ownerType, int $ownerId): array
    {
        $res = $this->database->table('awt_storage')->select(["id"])->where(["ownerType" => $ownerType->value, "ownerId" => $ownerId])->get();
        $result = [];
        foreach($res as $r) {
            $result[] = new StorageEntry($r['id']);
        }
        return $result;
    }

    public function fetchByOwnerAndName(int $ownerId, string $name): ?StorageEntry
    {
        $res = $this->database->table('awt_storage')->select(["id"])->where(["ownerId" => $ownerId, "name" => $name])->get();
        if(empty($res))
            return null;

        return new StorageEntry($res[0]['id']);
    }
}