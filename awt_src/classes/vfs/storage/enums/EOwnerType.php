<?php

namespace vfs\storage\enums;

enum EOwnerType : string {
    case USER = 'user';
    case SYSTEM = 'system';
    case PACKAGE = 'package';
}