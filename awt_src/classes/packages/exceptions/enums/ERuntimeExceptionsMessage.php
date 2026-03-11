<?php

namespace packages\exceptions\enums;

enum ERuntimeExceptionsMessage: string
{
    case EXECUTION = "There was an error while executing the package: ";
    case NO_RUNTIME = "No runtime found for package: ";
    case INVALID_FLAG = "Invalid flag encountered: ";
    case CIRCULAR_DEPENDENCY = "Circular dependency detected: ";
    case MISSING_RUNTIME_API = "RuntimeAPI not found for linked file: ";
    case UNKNOWN_RUNTIME_EXCEPTION = "Unknown runtime exception: ";
}
