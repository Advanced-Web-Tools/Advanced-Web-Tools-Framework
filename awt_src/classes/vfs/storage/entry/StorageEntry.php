<?php

namespace vfs\storage\entry;

use model\Model;
use vfs\storage\entry\enums\EStorageEntryType;

class StorageEntry extends Model
{
    public string $name;
    public EStorageEntryType $type;
    public EStorageOwnerType $ownerType;


}