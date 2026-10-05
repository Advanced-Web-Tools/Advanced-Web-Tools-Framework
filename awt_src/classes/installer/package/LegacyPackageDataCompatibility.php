<?php
namespace installer\package;

use database\DatabaseManager;

/** Preserve DataManager metadata and @data URLs for newly installed old packages. */
final readonly class LegacyPackageDataCompatibility
{
    public function __construct(private DatabaseManager $database = new DatabaseManager()) {}

    public function register(string $source, int $packageId, string $packageName, array $manifest): void
    {
        $urls = [];
        foreach (['audio', 'image', 'video', 'icon', 'other', 'document'] as $type) {
            $directory = $source . '/' . $type;
            if (!is_dir($directory)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!$file->isFile()) continue;
                $name = substr($file->getPathname(), strlen($directory) + 1);
                $destination = DATA . "media/packages/{$type}/{$packageName}/{$name}";
                $parent = dirname($destination);
                if (!is_dir($parent) && !mkdir($parent, 0755, true) && !is_dir($parent)) {
                    throw new \RuntimeException("Cannot create legacy package data directory: {$parent}");
                }
                if (!copy($file->getPathname(), $destination)) throw new \RuntimeException("Cannot copy package data: {$name}");
                $this->database->table('awt_data')->insert([
                    'ownerType' => 'Package', 'ownerName' => $packageName, 'ownerId' => $packageId,
                    'dataType' => $type, 'dataName' => $name,
                ])->executeInsert();
                $urls[$name] = "/awt_data/media/packages/{$type}/{$packageName}/{$name}";
            }
        }
        $update = [];
        foreach (['icon', 'preview_image'] as $field) {
            if (isset($manifest[$field], $urls[$manifest[$field]])) $update[$field] = $urls[$manifest[$field]];
        }
        if ($update !== []) $this->database->table('awt_package')->where(['id' => $packageId])->update($update);
    }
}
