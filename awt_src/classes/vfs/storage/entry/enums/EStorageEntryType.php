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
    case PHP      = 'php';
    case HTML     = 'html';
    case CSS      = 'css';
    case JS       = 'js';
    case JSON     = 'json';
    case XML      = 'xml';
    case TXT      = 'txt';
    case CSV      = 'csv';
    case SQL      = 'sql';
    case Document = 'document';
    case Image    = 'image';
    case Audio    = 'audio';
    case Video    = 'video';
    case Other    = 'other';

    private const EXTENSION_MAP = [
        // Documents
        'pdf'  => self::Document,
        'doc'  => self::Document,
        'docx' => self::Document,
        'xls'  => self::Document,
        'xlsx' => self::Document,
        'ppt'  => self::Document,
        'pptx' => self::Document,

        // Images
        'jpg'  => self::Image,
        'jpeg' => self::Image,
        'png'  => self::Image,
        'gif'  => self::Image,
        'webp' => self::Image,
        'svg'  => self::Image,
        'bmp'  => self::Image,
        'ico'  => self::Image,

        // Audio
        'mp3'  => self::Audio,
        'wav'  => self::Audio,
        'ogg'  => self::Audio,
        'flac' => self::Audio,
        'aac'  => self::Audio,

        // Video
        'mp4'  => self::Video,
        'avi'  => self::Video,
        'mov'  => self::Video,
        'mkv'  => self::Video,
        'webm' => self::Video,
    ];

    public static function fromExtension(string $extension): self
    {
        $ext = strtolower(ltrim($extension, '.'));

        if ($byValue = self::tryFrom($ext))
            return $byValue;

        return self::EXTENSION_MAP[$ext] ?? self::Other;
    }
}