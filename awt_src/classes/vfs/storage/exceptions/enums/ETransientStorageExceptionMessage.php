<?php

namespace vfs\storage\exceptions\enums;

enum ETransientStorageExceptionMessage: string
{
    case NotFound    = 'Could not find';
    case NotSaved    = 'Could not save';
    case NotDeleted  = 'Could not delete';
    case NotCopied   = 'Could not copy';
    case NotMoved    = 'Could not move';
    case NotUploaded = 'Could not upload';
    case NotUpdated  = 'Could not update';
    case PoolNotCreated = 'Could not create pool';
    case PoolNotDeleted = 'Could not delete pool';
    case PoolNotSelected = 'Could not select pool';
}