<?php

namespace vfs\storage\entry\enums;

enum EStorageOwnerType: string
{
    case System = "system";
    case User = "user";
    case Package = "package";
}
