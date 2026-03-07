<?php

namespace vfs\storage\entry\repository;

use database\DatabaseManager;

final class StorageRepository
{
    private DatabaseManager $database;

    public function __construct()
    {
        $this->database = new DatabaseManager();
    }

}