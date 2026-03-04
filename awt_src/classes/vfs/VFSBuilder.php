<?php

namespace vfs;

use object\ObjectCollection;
use vfs\enums\EVFSType;

class VFSBuilder
{
    public VFSEntry $root;

    public function __construct()
    {
        $this->root = new VFSEntry("root", PACKAGES, EVFSType::Directory);
        $this->scanDirectory(PACKAGES, $this->root);
    }

    private function scanDirectory(string $path, VFSEntry $parent): void
    {
        $scanned = scandir($path);
        $scanned = array_slice($scanned, 2);

        foreach ($scanned as $item) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            $type = is_dir($fullPath) ? EVFSType::Directory : EVFSType::File;

            $entry = new VFSEntry($item, $fullPath, $type);
            $parent->addChild($entry);

            if ($type === EVFSType::Directory) {
                $this->scanDirectory($fullPath, $entry);
            }
        }
    }

    public function build(): ObjectCollection
    {
        return $this->root->children;
    }


    public function cache(): void
    {
        $map = $this->root->__toArray();

        $output = "<?php\n\n return " . var_export($map, true) . ";\n";
        file_put_contents(CACHE . "vfs.php", $output);
    }
}