<?php

namespace vfs\storage\exceptions;

enum EStorageExceptions
{
    case FileNotFound;
    case MiddlewareBlocking;
}
