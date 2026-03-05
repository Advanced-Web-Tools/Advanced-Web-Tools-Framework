<?php

namespace vfs\storage\enums;

enum EStorageFileType
{
    case Text;
    case Php;
    case Json;
    case Xml;
    case Image;
    case Audio;
    case Video;
    case Document;
    case Other;
}