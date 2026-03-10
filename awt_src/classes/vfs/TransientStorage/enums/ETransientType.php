<?php

namespace vfs\TransientStorage\enums;

enum ETransientType: string
{
    case PHP = 'php';
    case JSON = 'json';
    case XML = 'xml';
    case TXT = 'txt';
    case HTML = 'html';
    case JS = 'js';

    case CACHE = 'cache';

    case IMAGE = 'image';
    case VIDEO = 'video';
    case DOCUMENT = 'document';
    case ARCHIVE = 'archive';

    case FILE = 'file';

    /**
     * Resolve enum from file path
     */
    public static function fromPath(string $path): self
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return self::fromExtension($ext);
    }

    /**
     * Resolve enum from extension
     */
    public static function fromExtension(string $extension): self
    {
        $extension = strtolower(trim($extension, '.'));

        return match (true) {

            /* CODE / TEXT */
            in_array($extension, ['php']) => self::PHP,
            in_array($extension, ['json']) => self::JSON,
            in_array($extension, ['xml']) => self::XML,
            in_array($extension, ['txt', 'log', 'md']) => self::TXT,
            in_array($extension, ['html', 'htm']) => self::HTML,
            in_array($extension, ['js', 'mjs', 'cjs']) => self::JS,

            /* IMAGES */
            in_array($extension, [
                'jpg','jpeg','png','gif','webp','bmp','svg','tiff','ico','avif'
            ]) => self::IMAGE,

            /* VIDEOS */
            in_array($extension, [
                'mp4','webm','mkv','avi','mov','wmv','flv','m4v','3gp'
            ]) => self::VIDEO,

            /* DOCUMENTS */
            in_array($extension, [
                'pdf','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp','rtf'
            ]) => self::DOCUMENT,

            /* ARCHIVES */
            in_array($extension, [
                'zip','rar','7z','tar','gz','bz2','xz','tgz','tar.gz'
            ]) => self::ARCHIVE,

            /* CACHE */
            $extension === 'cache' => self::CACHE,

            default => self::FILE
        };
    }

    /**
     * Get canonical extension for this type
     */
    public function getDefaultExtension(): string
    {
        return match ($this) {
            self::PHP => 'php',
            self::JSON => 'json',
            self::XML => 'xml',
            self::TXT => 'txt',
            self::HTML => 'html',
            self::JS => 'js',
            self::CACHE => 'cache',
            self::IMAGE => 'png',
            self::VIDEO => 'mp4',
            self::DOCUMENT => 'pdf',
            self::ARCHIVE => 'zip',
            self::FILE => '',
        };
    }

    /**
     * Check if extension belongs to this type
     */
    public function matchesExtension(string $extension): bool
    {
        return self::fromExtension($extension) === $this;
    }

    /**
     * Helper checks
     */
    public function isBinary(): bool
    {
        return match ($this) {
            self::IMAGE,
            self::VIDEO,
            self::ARCHIVE,
            self::DOCUMENT => true,
            default => false,
        };
    }

    public function isText(): bool
    {
        return !$this->isBinary();
    }
}