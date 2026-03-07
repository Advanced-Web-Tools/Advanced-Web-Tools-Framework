<?php

namespace vfs\storage\exceptions\enums;

enum EStorageException: string
{
    case FileNotFound = 'File not found';
    case FileExistsButNotFound = 'File exists but not found';
    case FailedToWrite = 'Failed to write';
    case FailedToRead = 'Failed to read';
    case FailedToCreate = 'Failed to create';
    case FailedToDelete = 'Failed to delete';
    case FailedToMove = 'Failed to move';
    case FailedToCopy = 'Failed to copy';
}
