<?php

namespace vfs\resource\exceptions;

enum EResourceException
{
    case FileNotFound;
    case FileExistsButNotFound;
    case MissingContext;
    case MiddlewareBlocking;
}
