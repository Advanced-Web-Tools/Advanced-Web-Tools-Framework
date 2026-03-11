<?php

namespace vfs\storage;

class StorageManager
{
    private StorageRepository $repository;
    public function __construct()
    {
        $this->repository = new StorageRepository();
    }

    public static function upload(?string $name = null)
    {

    }

    public static function uploadMultiple()
    {

    }

    public static function download()
    {

    }

    public static function get(int $id)
    {

    }

    public static function move(int $id)
    {

    }

    public static function delete(int $id)
    {

    }

    public static function rename(int $id)
    {

    }

    public static function copy(int $id, string $name)
    {

    }

    public static function registerLocal(StorageEntry $entry)
    {

    }
}