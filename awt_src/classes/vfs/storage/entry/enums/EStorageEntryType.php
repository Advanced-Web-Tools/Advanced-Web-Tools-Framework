<?php

namespace vfs\storage\entry\enums;

/**
 * Enum representing different storage entry types.
 *
 * This enum is used to classify various types of files or data entries
 * based on their format or intended use.
 */
enum EStorageEntryType: string
{
    case PHP = 'php';
    case HTML = 'html';
    case CSS = 'css';
    case JS = 'js';
    case JSON = 'json';
    case XML = 'xml';
    case TXT = 'txt';
    case CSV = 'csv';
    case SQL = 'sql';
    case Document = 'document';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';
    case Other = 'other';
}