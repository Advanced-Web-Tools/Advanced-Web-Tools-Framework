<?php

namespace vfs\cache\enums;

enum ECacheValidation: string
{
    case NONE = 'none';
    case EXPIRE = 'expire';
    case EXPIRE_LONGER = 'expire_longer';
    case MODIFIED = 'modified';
    case HASH = 'hash';
}